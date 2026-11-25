<?php

namespace Modules\Identity\Services;

use App\Exceptions\BusinessException;
use Closure;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Support\IdentityPermissions;
use Spatie\Permission\PermissionRegistrar;

final class IdentityAccessMutation
{
    public function __construct(
        private readonly IdentityMutex $mutex,
        private readonly IdentityAccess $access,
    ) {}

    /**
     * Reusable transaction boundary for future authorized Identity Actions.
     * The callback must use native Spatie APIs on the same connection. No
     * production Users or Roles CRUD operations are exposed by Feature 01.
     */
    public function execute(int $actorId, string $permission, Closure $mutation): mixed
    {
        $this->mutex->ensureOuterTransactionAvailable();

        try {
            return DB::transaction(function () use ($actorId, $permission, $mutation): mixed {
                $manager = $this->mutex->lock();

                if (! $this->access->currentHasAll([$permission], $actorId)) {
                    throw new BusinessException(__('identity.access_denied'), 403, 'IDENTITY_ACCESS_DENIED');
                }

                $result = $mutation();

                // Preserve the existing permanent serialization record.
                $currentManager = $this->mutex->lock();
                $reception = DB::table('roles')
                    ->where('name', 'reception')->where('guard_name', 'web')
                    ->lockForUpdate()->first();

                if ((int) $currentManager->id !== (int) $manager->id || ! $currentManager->is_active
                    || $reception === null || $reception->name !== 'reception'
                    || $reception->guard_name !== 'web' || ! (bool) $reception->is_active) {
                    throw new BusinessException(__('identity.permanent_roles'), errorCode: 'IDENTITY_PERMANENT_ROLES');
                }

                if (! $this->access->currentHasAll(IdentityPermissions::ESSENTIAL)) {
                    throw new BusinessException(__('identity.last_admin'), errorCode: 'IDENTITY_LAST_ADMIN');
                }

                return $result;
            });
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
