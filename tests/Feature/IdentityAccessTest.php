<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Actions\SeedIdentityCatalogueAction;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Services\IdentityAccess;
use Modules\Identity\Services\IdentityAccessMutation;
use Modules\Identity\Services\IdentityMutex;
use Modules\Identity\Support\IdentityPermissions;
use Spatie\Permission\PermissionRegistrar;

class IdentityAccessTest extends IdentityDatabaseTestCase
{
    public function test_fresh_eligibility_and_real_permissions_fail_closed(): void
    {
        app(SeedIdentityCatalogueAction::class)->execute();
        $manager = Role::findByName('manager', 'web');
        $user = User::factory()->create();
        $access = app(IdentityAccess::class);
        $permission = Permission::where('name', 'identity.users.view')->sole();

        $this->assertFalse($access->isEligible($user->id));
        $user->syncRoles([$manager]);
        $this->assertTrue($access->allows($user->id, $permission->name));

        app(PermissionRegistrar::class)->getPermissions();
        $user->load('roles.permissions');
        $manager->revokePermissionTo($permission);
        $this->assertFalse($access->allows($user->id, $permission->name));

        $user->givePermissionTo($permission); // Deliberate test-only corrupted direct grant.
        $this->assertFalse($access->isEligible($user->id));
        $user->revokePermissionTo($permission);
        $this->assertTrue($access->isEligible($user->id));

        $user->update(['is_active' => false]);
        $this->assertFalse($access->isEligible($user->id));
        $user->update(['is_active' => true]);
        $manager->update(['is_active' => false]);
        $this->assertFalse($access->isEligible($user->id));
        $manager->update(['is_active' => true]);
        $manager->update(['guard_name' => 'other']);
        $this->assertFalse($access->isEligible($user->id));
    }

    public function test_viability_depends_on_six_permissions_not_role_name_or_founder(): void
    {
        app(SeedIdentityCatalogueAction::class)->execute();
        $manager = Role::findByName('manager', 'web');
        $founder = User::factory()->create();
        $founder->syncRoles([$manager]);
        $this->assertSame(1, $founder->id);

        $manager->revokePermissionTo(Permission::where('name', 'identity.users.view')->sole());
        $this->assertFalse($this->viable());

        $custom = Role::create(['name' => 'custom-admin', 'guard_name' => 'web', 'alias_name' => 'Custom admin']);
        $custom->syncPermissions(Permission::whereIn('name', IdentityPermissions::ESSENTIAL)->get());
        $staff = User::factory()->create();
        $staff->syncRoles([$custom]);
        $this->assertTrue($this->viable());

        $custom->revokePermissionTo(Permission::where('name', 'identity.roles.show')->sole());
        $this->assertFalse($this->viable());
    }

    public function test_transaction_boundary_rolls_back_last_admin_loss(): void
    {
        app(SeedIdentityCatalogueAction::class)->execute();
        $manager = Role::findByName('manager', 'web');
        $founder = User::factory()->create();
        $founder->syncRoles([$manager]);
        $grant = Permission::where('name', 'identity.users.view')->sole();

        try {
            app(IdentityAccessMutation::class)->execute($founder->id, 'identity.roles.update', function () use ($manager, $grant): void {
                $manager->revokePermissionTo($grant);
            });
            $this->fail('Removing the last viable path must be rejected.');
        } catch (BusinessException $exception) {
            $this->assertSame(409, $exception->httpStatus);
            $this->assertSame('IDENTITY_LAST_ADMIN', $exception->errorCode);
        }

        $this->assertTrue(app(IdentityAccess::class)->allows($founder->id, $grant->name));
        $this->assertTrue($founder->fresh()->hasPermissionTo($grant->name));
        $this->assertTrue($this->viable());
    }

    public function test_authorized_role_replacement_is_atomic_and_roles_are_permanent(): void
    {
        app(SeedIdentityCatalogueAction::class)->execute();
        $manager = Role::findByName('manager', 'web');
        $reception = Role::findByName('reception', 'web');
        $founder = User::factory()->create();
        $founder->syncRoles([$manager]);
        $other = User::factory()->create();
        $other->syncRoles([$reception]);
        $boundary = app(IdentityAccessMutation::class);

        $boundary->execute($founder->id, 'identity.users.update', fn () => $other->syncRoles([$manager]));
        $this->assertSame([$manager->id], $other->roles()->pluck('roles.id')->all());
        $this->assertSame(0, $other->permissions()->count());

        try {
            $boundary->execute($founder->id, 'identity.roles.update', fn () => $reception->update(['is_active' => false]));
            $this->fail('Permanent reception role cannot be disabled.');
        } catch (BusinessException $exception) {
            $this->assertSame('IDENTITY_PERMANENT_ROLES', $exception->errorCode);
        }

        $this->assertTrue($reception->fresh()->is_active);
    }

    public function test_actor_without_permission_cannot_mutate(): void
    {
        app(SeedIdentityCatalogueAction::class)->execute();
        $user = User::factory()->create();
        $user->syncRoles([Role::findByName('reception', 'web')]);
        $called = false;

        try {
            app(IdentityAccessMutation::class)->execute($user->id, 'identity.roles.update', function () use (&$called): void {
                $called = true;
            });
            $this->fail('Unauthorized actor must fail closed.');
        } catch (BusinessException $exception) {
            $this->assertSame(403, $exception->httpStatus);
        }

        $this->assertFalse($called);
    }

    private function viable(): bool
    {
        return DB::transaction(function (): bool {
            app(IdentityMutex::class)->lock();

            return app(IdentityAccess::class)->currentHasAll(IdentityPermissions::ESSENTIAL);
        });
    }
}
