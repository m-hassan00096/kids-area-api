<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Tests\Support\AuthenticationRaceHarness;
use Tests\Support\UsersRaceFixtures;

class RolesSecurityTest extends AuthenticationHttpTestCase
{
    use AuthenticationRaceHarness, UsersRaceFixtures;

    public function test_invalid_generation_and_anomalous_memberships_fail_closed_without_partial_mutation(): void
    {
        $manager = $this->user('Fixture.RoleBoundsAdmin');
        $role = Role::create(['name' => 'Fixture Bounds', 'guard_name' => 'web']);
        $role->givePermissionTo('identity.roles.show');
        $valid = $this->user('Fixture.BoundsValid', $role->name);
        $invalid = $this->user('Fixture.BoundsInvalid', $role->name, ['is_active' => false]);
        $server = $this->server();
        $admin = $this->browser($server);
        $device = $this->browser($server);
        $this->assertSame(200, $admin->login($manager->username)->status);
        $this->assertSame(200, $device->login($valid->username)->status);
        $path = '/api/v1/roles/'.$role->id;
        $fields = ['name' => $role->name, 'isActive' => true, 'permissions' => ['identity.roles.show']];
        $this->assertSame(200, $admin->request('PUT', $path, $fields)->status);
        $this->assertSame($valid->auth_version, $valid->fresh()->auth_version);
        $this->assertSame(200, $device->me()->status);
        $before = $role->fresh()->getRawOriginal();
        $binding = DB::table('sessions')->where('auth_user_id', $valid->id)->first();
        foreach ([0, PHP_INT_MAX] as $version) {
            DB::table('users')->where('id', $invalid->id)->update(['auth_version' => $version]);
            $response = $admin->request('PUT', $path, ['name' => 'Must not change', 'isActive' => false, 'permissions' => []], [
                'headers' => ['X-Identity-Test-Inspect' => '1'],
            ]);
            $this->assertSame(500, $response->status);
            $this->assertSame('rolled_back', $response->header('X-Test-Roles-Outcome'));
            $this->assertSame('1', $response->header('X-Test-Redacted'));
            $this->assertSame($before, $role->fresh()->getRawOriginal());
            $this->assertSame(['identity.roles.show'], $role->permissions()->pluck('permissions.name')->all());
            $this->assertSame($version, $invalid->fresh()->auth_version);
            $this->assertSame($valid->auth_version, $valid->fresh()->auth_version);
            $this->assertEquals($binding, DB::table('sessions')->where('auth_user_id', $valid->id)->first());
        }
        $this->assertSame(200, $device->me()->status);

        foreach ([['FixtureOtherMorph', $manager->id], [(new User)->getMorphClass(), 999999]] as $i => [$morph, $id]) {
            $anomaly = Role::create(['name' => 'Fixture Member Anomaly '.$i, 'guard_name' => 'web']);
            DB::table('model_has_roles')->insert(['role_id' => $anomaly->id, 'model_type' => $morph, 'model_id' => $id]);
            $uri = '/api/v1/roles/'.$anomaly->id;
            $this->assertSame(409, $admin->request('PUT', $uri, ['name' => $anomaly->name, 'isActive' => false, 'permissions' => []])->status);
            $this->assertSame(409, $admin->request('DELETE', $uri)->status);
            $this->assertTrue($anomaly->fresh()->is_active);
            $this->assertSame(1, DB::table('model_has_roles')->where('role_id', $anomaly->id)->count());
        }
        $other = Role::create(['name' => 'Fixture Wrong Guard', 'guard_name' => 'other']);
        $this->assertSame(404, $admin->request('PUT', '/api/v1/roles/'.$other->id, $fields)->status);
        $this->assertSame(404, $admin->request('DELETE', '/api/v1/roles/'.$other->id)->status);
        $this->assertSame('other', $other->fresh()->guard_name);
    }

    public function test_role_disable_retires_already_admitted_waiting_role_mutation(): void
    {
        $manager = $this->user('Fixture.StaleRoleAdmin');
        $role = Role::create(['name' => 'Fixture Stale Role', 'guard_name' => 'web']);
        $role->givePermissionTo('identity.roles.update');
        $staff = $this->user('Fixture.StaleRoleStaff', $role->name);
        $target = Role::create(['name' => 'Fixture Stale Target', 'guard_name' => 'web']);
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $this->assertSame(200, $a->login($manager->username)->status);
        $this->assertSame(200, $b->login($staff->username)->status);
        [$first, $second] = $this->race(
            $a->prepare('PUT', '/api/v1/roles/'.$role->id, ['name' => $role->name, 'isActive' => false, 'permissions' => ['identity.roles.update']], $this->raceOptions('hold-role-write', 'first')),
            $b->prepare('PUT', '/api/v1/roles/'.$target->id, ['name' => 'Must not commit', 'isActive' => true, 'permissions' => []], $this->raceOptions('observe', 'second')),
            'manager',
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(401, $second->status);
        $this->assertSame('rolled_back', $second->header('X-Test-Roles-Outcome'));
        $this->assertSame($target->name, $target->fresh()->name);
        $this->assertSame($staff->auth_version + 1, $staff->fresh()->auth_version);
        $this->assertSame(200, $a->request('PUT', '/api/v1/roles/'.$role->id, ['name' => $role->name, 'isActive' => true, 'permissions' => ['identity.roles.update']])->status);
        $this->assertSame(401, $b->me()->status);
        $this->assertSame(200, $b->login($staff->username)->status);
    }

    public function test_serialized_disable_and_reenable_never_restore_old_generation_or_assign_before_enable(): void
    {
        $one = $this->user('Fixture.EnableAdminOne');
        $two = $this->user('Fixture.EnableAdminTwo');
        $role = Role::create(['name' => 'Fixture Reenable Race', 'guard_name' => 'web']);
        $role->givePermissionTo('identity.roles.show');
        $staff = $this->user('Fixture.EnableStaff', $role->name);
        $candidate = $this->user('Fixture.EnableAssignment', 'reception');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $old = $this->browser($a->server);
        $this->assertSame(200, $a->login($one->username)->status);
        $this->assertSame(200, $b->login($two->username)->status);
        $this->assertSame(200, $old->login($staff->username)->status);
        $path = '/api/v1/roles/'.$role->id;
        $active = ['name' => $role->name, 'isActive' => true, 'permissions' => ['identity.roles.show']];
        $inactive = array_replace($active, ['isActive' => false]);
        [$first, $second] = $this->race(
            $a->prepare('PUT', $path, $inactive, $this->raceOptions('hold-role-write', 'first')),
            $b->prepare('PUT', $path, $active, $this->raceOptions('observe', 'second')),
            'manager',
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(200, $second->status);
        $this->assertTrue($role->fresh()->is_active);
        $this->assertSame($staff->auth_version + 1, $staff->fresh()->auth_version);
        $this->assertSame(401, $old->me()->status);
        $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $staff->id)->count());
        $this->assertSame(200, $old->login($staff->username)->status);
        $this->assertSame(200, $a->request('PUT', $path, $inactive)->status);

        // A concurrent assignment sees the last committed inactive state while
        // enable is held. It must fail validation, not infer future eligibility.
        $before = $candidate->fresh()->getRawOriginal();
        $fields = $this->fields($candidate, $role->name);
        [$first, $second] = $this->race(
            $a->prepare('PUT', $path, $active, $this->raceOptions('hold-role-write', 'first')),
            $b->prepare('PUT', '/api/v1/users/'.$candidate->id, $fields, $this->raceOptions('observe', 'second')),
            null,
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(422, $second->status);
        $this->assertArrayHasKey('roleId', $second->json()['errors']);
        $this->assertSame($before, $candidate->fresh()->getRawOriginal());
        $this->assertSame('reception', $candidate->fresh()->roles()->sole()->name);
        $this->assertTrue($role->fresh()->is_active);
        $this->assertSame($staff->auth_version + 2, $staff->fresh()->auth_version);
        $this->assertSame(401, $old->me()->status);
        $this->assertSame(200, $b->request('PUT', '/api/v1/users/'.$candidate->id, $fields)->status);
        $this->assertSame($role->id, $candidate->fresh()->roles()->sole()->id);
        $this->assertSame($candidate->auth_version, $candidate->fresh()->auth_version);

        // Conversely, when enable wins and an already-admitted disable is
        // waiting, the subsequent true-to-false transition must revoke once.
        $this->assertSame(200, $a->request('PUT', $path, $inactive)->status);
        $version = $staff->fresh()->auth_version;
        $assignedVersion = $candidate->fresh()->auth_version;
        [$first, $second] = $this->race(
            $a->prepare('PUT', $path, $active, $this->raceOptions('hold-role-write', 'first')),
            $b->prepare('PUT', $path, $inactive, $this->raceOptions('observe', 'second')),
            'manager',
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(200, $second->status);
        $this->assertFalse($role->fresh()->is_active);
        $this->assertSame($version + 1, $staff->fresh()->auth_version);
        $this->assertSame($assignedVersion + 1, $candidate->fresh()->auth_version);
        $this->assertSame(0, DB::table('sessions')->whereIn('auth_user_id', [$staff->id, $candidate->id])->count());
        $this->assertSame(200, $a->me()->status);
        $this->assertSame(200, $b->me()->status);
    }
}
