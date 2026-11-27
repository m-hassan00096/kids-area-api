<?php

namespace Modules\Identity\Actions;

use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Services\ActivityCatalogueGrants;
use Modules\Identity\Services\IdentityMutex;
use Modules\Identity\Support\PermissionCatalogue;
use Spatie\Permission\PermissionRegistrar;

final class SeedIdentityCatalogueAction
{
    public function __construct(private readonly IdentityMutex $mutex, private readonly ActivityCatalogueGrants $grants) {}

    public function execute(): void
    {
        $this->mutex->ensureOuterTransactionAvailable();

        try {
            DB::transaction(function (): void {
                // The native unique key makes createOrFirst safe even on a first-install race.
                $candidate = Role::query()->createOrFirst(
                    ['name' => 'manager', 'guard_name' => 'web'],
                    ['is_active' => true],
                );

                $manager = $this->mutex->lock();
                $fresh = $candidate->wasRecentlyCreated && $this->isFreshCatalogue((int) $manager->id);

                $reception = Role::query()->createOrFirst(
                    ['name' => 'reception', 'guard_name' => 'web'],
                    ['is_active' => true],
                );
                if ($reception->name !== 'reception' || $reception->guard_name !== 'web') {
                    throw new BusinessException(__('identity.seed_prerequisites'), errorCode: 'IDENTITY_SEED_REQUIRED');
                }

                $permissions = collect();
                foreach (PermissionCatalogue::definitions() as $name => $alias) {
                    $permission = Permission::query()->createOrFirst(
                        ['name' => $name, 'guard_name' => 'web'],
                        ['alias_name' => $alias],
                    );
                    if ($permission->name !== $name || $permission->guard_name !== 'web'
                        || ! is_string($permission->alias_name) || trim($permission->alias_name) === '') {
                        throw new BusinessException(__('identity.seed_prerequisites'), errorCode: 'IDENTITY_SEED_REQUIRED');
                    }
                    $permissions->push($permission);
                }

                if ($fresh) {
                    // Fresh-only defaults and the completion row are one DML transaction.
                    $this->grants->complete($manager, $reception, $permissions, fresh: true);
                }
            }, 3); // Only this entirely replay-safe additive transaction may retry a deadlock.
        } finally {
            // Native mutation methods also invalidate their cache; clear after commit or rollback.
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    private function isFreshCatalogue(int $managerId): bool
    {
        if (DB::table('roles')->where('id', '!=', $managerId)->orderBy('id')->lockForUpdate()->first() !== null) {
            return false;
        }

        foreach (['users', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions'] as $table) {
            if (DB::table($table)->lockForUpdate()->first() !== null) {
                return false;
            }
        }

        return true;
    }
}
