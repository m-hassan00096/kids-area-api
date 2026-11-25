<?php

namespace Modules\Identity\Actions;

use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Services\FounderSequence;
use Modules\Identity\Services\IdentityAccess;
use Modules\Identity\Services\IdentityMutex;
use Modules\Identity\Services\InitialUserData;
use Modules\Identity\Support\IdentityPermissions;
use SensitiveParameter;

final class BootstrapManagerAction
{
    public function __construct(
        private readonly InitialUserData $data,
        private readonly IdentityMutex $mutex,
        private readonly FounderSequence $sequence,
        private readonly IdentityAccess $access,
    ) {}

    public function execute(#[SensitiveParameter] array $credentials): User
    {
        $this->mutex->ensureOuterTransactionAvailable();
        $attributes = $this->data->prepare($credentials);

        // Do not emit password-hash SQL bindings to query logging/listeners.
        $connection = DB::connection();
        $logging = $connection->logging();
        $dispatcher = $connection->getEventDispatcher();
        $connection->disableQueryLog();
        $connection->unsetEventDispatcher();

        try {
            return DB::transaction(function () use ($attributes): User {
                $manager = $this->mutex->lock();
                $this->assertCatalogue($manager);

                if (DB::table('users')->orderBy('id')->lockForUpdate()->first() !== null) {
                    throw new BusinessException(__('identity.users_exist'), errorCode: 'IDENTITY_USERS_EXIST');
                }

                $this->sequence->assertFirstIdAvailable();

                $user = User::query()->create($attributes);
                if ((int) $user->id !== 1) {
                    throw new BusinessException(__('identity.founder_id'), errorCode: 'IDENTITY_FOUNDER_ID_UNAVAILABLE');
                }

                $user->syncRoles([$manager]);
                if (! $this->access->currentHasAll(IdentityPermissions::keys(), (int) $user->id)) {
                    throw new BusinessException(__('identity.bootstrap_failed'), errorCode: 'IDENTITY_BOOTSTRAP_INCOMPLETE');
                }

                return $user;
            }); // Never retry automatically after an ID has been allocated.
        } finally {
            if ($dispatcher !== null) {
                $connection->setEventDispatcher($dispatcher);
            }
            if ($logging) {
                $connection->enableQueryLog();
            }
        }
    }

    private function assertCatalogue(Role $manager): void
    {
        $reception = DB::table('roles')->where('name', 'reception')->where('guard_name', 'web')
            ->lockForUpdate()->first();

        $permissions = DB::table('permissions')
            ->whereIn('name', IdentityPermissions::keys())->where('guard_name', 'web')
            ->orderBy('id')->lockForUpdate()->get(['id', 'name', 'guard_name'])
            ->filter(fn ($permission) => $permission->guard_name === 'web' && in_array($permission->name, IdentityPermissions::keys(), true));

        $ids = $permissions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $granted = DB::table('role_has_permissions')->where('role_id', $manager->id)
            ->orderBy('permission_id')->lockForUpdate()->pluck('permission_id')
            ->map(fn ($id) => (int) $id)->all();

        if (! $manager->is_active || $reception === null || $reception->name !== 'reception'
            || $reception->guard_name !== 'web' || ! (bool) $reception->is_active
            || $permissions->pluck('name')->unique()->count() !== count(IdentityPermissions::keys())
            || array_diff($ids, $granted) !== []) {
            throw new BusinessException(__('identity.seed_prerequisites'), errorCode: 'IDENTITY_SEED_REQUIRED');
        }
    }
}
