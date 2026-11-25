<?php

namespace Modules\Identity\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Services\IdentityMutex;
use Modules\Identity\Support\IdentityPermissions;
use Spatie\Permission\PermissionRegistrar;

final class SeedIdentityCatalogueAction
{
    public function __construct(private readonly IdentityMutex $mutex) {}

    public function execute(): void
    {
        $this->mutex->ensureOuterTransactionAvailable();

        try {
            DB::transaction(function (): void {
                // The native unique key makes createOrFirst safe even on a first-install race.
                $candidate = Role::query()->createOrFirst(
                    ['name' => 'manager', 'guard_name' => 'web'],
                    ['alias_name' => 'مدير', 'is_active' => true],
                );

                $manager = $this->mutex->lock();
                $fresh = $candidate->wasRecentlyCreated && $this->isFreshCatalogue((int) $manager->id);

                Role::query()->createOrFirst(
                    ['name' => 'reception', 'guard_name' => 'web'],
                    ['alias_name' => 'استقبال', 'is_active' => true],
                );

                $permissions = collect();
                foreach (IdentityPermissions::CATALOGUE as $name => $alias) {
                    $permissions->push(Permission::query()->createOrFirst(
                        ['name' => $name, 'guard_name' => 'web'],
                        ['alias_name' => $alias],
                    ));
                }

                if ($fresh) {
                    // Pass native models, not cached permission-name lookups.
                    $manager->syncPermissions($permissions);
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
