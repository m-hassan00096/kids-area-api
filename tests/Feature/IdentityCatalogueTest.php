<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Actions\SeedIdentityCatalogueAction;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Support\IdentityPermissions;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class IdentityCatalogueTest extends IdentityDatabaseTestCase
{
    public function test_fresh_catalogue_exact_grants_and_no_accounts(): void
    {
        $this->assertSame(0, Artisan::call('db:seed', ['--force' => true]), Artisan::output());

        $this->assertSame(['manager', 'reception'], Role::orderBy('name')->pluck('name')->all());
        $this->assertSame(2, Role::where('guard_name', 'web')->where('is_active', true)->count());
        $this->assertEquals(IdentityPermissions::CATALOGUE, Permission::pluck('alias_name', 'name')->all());
        $this->assertSame(10, Permission::where('guard_name', 'web')->count());

        $manager = Role::findByName('manager', 'web');
        $reception = Role::findByName('reception', 'web');
        $this->assertSame('مدير', $manager->alias_name);
        $this->assertSame('استقبال', $reception->alias_name);
        $this->assertEqualsCanonicalizing(IdentityPermissions::keys(), $manager->permissions()->pluck('name')->all());
        $this->assertSame(0, $reception->permissions()->count());
        $this->assertSame(0, User::count());
        $this->assertSame(0, DB::table('model_has_roles')->count());
    }

    public function test_reseed_preserves_customizations_and_does_not_restore_grants(): void
    {
        $action = app(SeedIdentityCatalogueAction::class);
        $action->execute();
        $manager = Role::findByName('manager', 'web');
        $permission = Permission::where('name', 'identity.users.view')->sole();
        $manager->update(['alias_name' => 'Custom alias']);
        $permission->update(['alias_name' => 'Custom permission']);
        $manager->revokePermissionTo($permission);
        $missing = Permission::where('name', 'identity.audit.view')->sole();
        $missing->delete();
        $user = User::factory()->create(['is_active' => false]);
        $user->syncRoles([$manager]);
        $beforeUser = $user->getRawOriginal();
        $beforeGrants = $manager->permissions()->orderBy('permissions.id')->pluck('permissions.id')->all();

        app(PermissionRegistrar::class)->getPermissions();
        $action->execute();
        $action->execute();

        $this->assertSame(10, Permission::count());
        $this->assertSame(2, Role::count());
        $this->assertSame('Custom alias', $manager->fresh()->alias_name);
        $this->assertSame('Custom permission', $permission->fresh()->alias_name);
        $this->assertSame($beforeGrants, $manager->permissions()->orderBy('permissions.id')->pluck('permissions.id')->all());
        $this->assertSame($beforeUser, $user->fresh()->getRawOriginal());
        $this->assertFalse($user->fresh()->hasPermissionTo('identity.users.view'));
        $this->assertFalse($user->fresh()->hasPermissionTo('identity.audit.view'));
    }

    public function test_partial_install_does_not_get_fresh_install_grants(): void
    {
        Permission::create([
            'name' => 'identity.users.view',
            'guard_name' => 'web',
            'alias_name' => 'Pre-existing',
        ]);

        app(SeedIdentityCatalogueAction::class)->execute();

        $this->assertSame(10, Permission::count());
        $this->assertSame(2, Role::count());
        $this->assertSame('Pre-existing', Permission::where('name', 'identity.users.view')->sole()->alias_name);
        $this->assertSame(0, Role::findByName('manager', 'web')->permissions()->count());
        $this->assertSame(0, User::count());
    }

    public function test_seed_failure_rolls_back_new_catalogue(): void
    {
        Permission::creating(function (Permission $permission): void {
            if ($permission->name === 'identity.roles.show') {
                throw new RuntimeException('Synthetic seed failure');
            }
        });

        try {
            app(SeedIdentityCatalogueAction::class)->execute();
            $this->fail('The synthetic failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic seed failure', $exception->getMessage());
        }

        $this->assertSame(0, Role::count());
        $this->assertSame(0, Permission::count());
        $this->assertSame(0, DB::table('role_has_permissions')->count());
        $this->assertSame(0, User::count());
    }
}
