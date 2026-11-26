<?php

namespace Tests\Support;

use Modules\Identity\Actions\SeedIdentityCatalogueAction;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Support\IdentityPermissions;

trait ActivityProvisionFixtures
{
    private function legacyActivityRoles(bool $seedDefinitions = true): array
    {
        $manager = Role::create(['name' => 'manager', 'guard_name' => 'web', 'is_active' => true]);
        $reception = Role::create(['name' => 'reception', 'guard_name' => 'web', 'is_active' => true]);
        foreach (IdentityPermissions::CATALOGUE as $name => $alias) {
            $permission = Permission::create(['name' => $name, 'guard_name' => 'web', 'alias_name' => $alias]);
            if ($name !== 'identity.audit.view') {
                $manager->givePermissionTo($permission);
            }
        }
        if ($seedDefinitions) {
            app(SeedIdentityCatalogueAction::class)->execute();
        }

        return [$manager->fresh(), $reception->fresh()];
    }

    private function grantKeys(Role $role): array
    {
        return $role->permissions()->orderBy('permissions.name')->pluck('permissions.name')->all();
    }
}
