<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Models\Role;
use Modules\Identity\Support\IdentityPermissions;
use Tests\Support\AuthenticationRaceHarness;
use Tests\Support\UsersRaceFixtures;

class RolesConcurrencyTest extends AuthenticationHttpTestCase
{
    use AuthenticationRaceHarness, UsersRaceFixtures;

    public function test_competing_create_and_rename_names_follow_actual_collation_in_both_orders(): void
    {
        $admin = $this->user('Fixture.RoleRaceAdmin');
        $other = $this->user('Fixture.RoleRaceOther');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $this->assertSame(200, $a->login($admin->username)->status);
        $this->assertSame(200, $b->login($other->username)->status);

        [$first, $second] = $this->race(
            $a->prepare('POST', '/api/v1/roles', ['name' => 'Fixture Unique Role', 'permissions' => ['identity.roles.show']], $this->raceOptions('hold-role-insert', 'first')),
            $b->prepare('POST', '/api/v1/roles', ['name' => 'fixture unique role', 'permissions' => ['identity.users.delete']], $this->raceOptions('observe', 'second')),
            'manager',
        );
        $this->assertSame(201, $first->status);
        $this->assertSame(422, $second->status);
        $this->assertArrayHasKey('name', $second->json()['errors']);
        $this->assertSame('rolled_back', $second->header('X-Test-Roles-Outcome'));
        $this->assertSame(1, Role::where('name', 'Fixture Unique Role')->count());
        $winner = Role::findByName('Fixture Unique Role', 'web');
        $this->assertSame(['identity.roles.show'], $winner->permissions()->pluck('permissions.name')->all());

        foreach ([true, false] as $createFirst) {
            $suffix = $createFirst ? 'First' : 'Second';
            $target = Role::create(['name' => 'Fixture Rename '.$suffix, 'guard_name' => 'web']);
            $target->givePermissionTo('identity.users.view');
            $original = $target->fresh()->getRawOriginal();
            $name = 'Fixture Claimed '.$suffix;
            $create = $a->prepare('POST', '/api/v1/roles', ['name' => $name, 'permissions' => []],
                $this->raceOptions($createFirst ? 'hold-role-insert' : 'observe', $createFirst ? 'first' : 'second'));
            $rename = $b->prepare('PUT', '/api/v1/roles/'.$target->id, [
                'name' => strtolower($name), 'isActive' => true, 'permissions' => ['identity.roles.show'],
            ], $this->raceOptions($createFirst ? 'observe' : 'hold-role-write', $createFirst ? 'second' : 'first'));
            [$first, $second] = $createFirst ? $this->race($create, $rename, 'manager') : $this->race($rename, $create, 'manager');
            $this->assertSame($createFirst ? 201 : 200, $first->status);
            $this->assertSame(422, $second->status);
            $this->assertArrayHasKey('name', $second->json()['errors']);
            $this->assertSame('rolled_back', $second->header('X-Test-Roles-Outcome'));
            $this->assertSame(1, Role::where('name', $name)->count());
            if ($createFirst) {
                $this->assertSame($original, $target->fresh()->getRawOriginal());
                $this->assertSame(['identity.users.view'], $target->permissions()->pluck('permissions.name')->all());
            } else {
                $this->assertSame(strtolower($name), $target->fresh()->name);
                $this->assertSame(['identity.roles.show'], $target->permissions()->pluck('permissions.name')->all());
            }
        }
        $this->assertSame(200, $a->me()->status);
        $this->assertSame(200, $b->me()->status);
    }

    public function test_delete_and_assign_role_recheck_current_membership_in_both_orders(): void
    {
        $one = $this->user('Fixture.RoleDeleteAdmin');
        $two = $this->user('Fixture.RoleAssignAdmin');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $this->assertSame(200, $a->login($one->username)->status);
        $this->assertSame(200, $b->login($two->username)->status);
        foreach ([true, false] as $deleteFirst) {
            $suffix = $deleteFirst ? 'Deleted' : 'Assigned';
            $role = Role::create(['name' => 'Fixture '.$suffix, 'guard_name' => 'web']);
            $role->givePermissionTo('identity.users.view');
            $user = $this->user('Fixture.Assign.'.$suffix, 'reception');
            $before = $user->fresh()->getRawOriginal();
            $path = '/api/v1/roles/'.$role->id;
            $delete = $a->prepare('DELETE', $path, options: $this->raceOptions($deleteFirst ? 'hold-role-delete' : 'observe', $deleteFirst ? 'first' : 'second'));
            $fields = array_replace($this->fields($user, $role->name), ['name' => 'Changed by assignment']);
            $assign = $b->prepare('PUT', '/api/v1/users/'.$user->id, $fields,
                $this->raceOptions($deleteFirst ? 'observe' : 'hold-users-update', $deleteFirst ? 'second' : 'first'));
            [$first, $second] = $deleteFirst ? $this->race($delete, $assign, 'manager') : $this->race($assign, $delete, 'manager');
            $this->assertSame(200, $first->status);
            if ($deleteFirst) {
                $this->assertSame(422, $second->status);
                $this->assertArrayHasKey('roleId', $second->json()['errors']);
                $this->assertSame('rolled_back', $second->header('X-Test-Users-Outcome'));
                $this->assertNull($role->fresh());
                $this->assertSame($before, $user->fresh()->getRawOriginal());
                $this->assertSame('reception', $user->fresh()->roles()->sole()->name);
                $this->assertFalse(DB::table('model_has_roles')->where('role_id', $role->id)->exists());
            } else {
                $this->assertSame(409, $second->status);
                $this->assertSame('IDENTITY_ROLE_ASSIGNED', $second->json()['errors']['code']);
                $this->assertSame('rolled_back', $second->header('X-Test-Roles-Outcome'));
                $this->assertNotNull($role->fresh());
                $this->assertSame($role->id, $user->fresh()->roles()->sole()->id);
                $this->assertSame('Changed by assignment', $user->fresh()->name);
                $this->assertSame(['identity.users.view'], $role->permissions()->pluck('permissions.name')->all());
            }
        }
    }

    public function test_role_disable_and_assignment_both_orders_preserve_authoritative_membership(): void
    {
        $one = $this->user('Fixture.RoleDisableAdmin');
        $two = $this->user('Fixture.RoleAssignAdmin');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $this->assertSame(200, $a->login($one->username)->status);
        $this->assertSame(200, $b->login($two->username)->status);
        foreach ([true, false] as $disableFirst) {
            $suffix = $disableFirst ? 'Before' : 'After';
            $role = Role::create(['name' => 'Fixture Disable '.$suffix, 'guard_name' => 'web']);
            $role->givePermissionTo('identity.users.view');
            $user = $this->user('Fixture.DisabledAssignment.'.$suffix, 'reception');
            $device = $this->browser($a->server);
            $this->assertSame(200, $device->login($user->username)->status);
            $disable = $a->prepare('PUT', '/api/v1/roles/'.$role->id, $this->roleFields($role, false),
                $this->raceOptions($disableFirst ? 'hold-role-write' : 'observe', $disableFirst ? 'first' : 'second'));
            $assign = $b->prepare('PUT', '/api/v1/users/'.$user->id, array_replace($this->fields($user, $role->name), ['name' => 'Assigned before disable']),
                $this->raceOptions($disableFirst ? 'observe' : 'hold-users-update', $disableFirst ? 'second' : 'first'));
            [$first, $second] = $disableFirst ? $this->race($disable, $assign, 'manager') : $this->race($assign, $disable, 'manager');
            $this->assertSame(200, $first->status);
            $this->assertSame($disableFirst ? 422 : 200, $second->status);
            $this->assertFalse($role->fresh()->is_active);
            $this->assertTrue($user->fresh()->is_active);
            if ($disableFirst) {
                $this->assertArrayHasKey('roleId', $second->json()['errors']);
                $this->assertSame('reception', $user->fresh()->roles()->sole()->name);
                $this->assertSame($user->auth_version, $user->fresh()->auth_version);
                $this->assertSame(200, $device->me()->status);
            } else {
                $this->assertSame($role->id, $user->fresh()->roles()->sole()->id);
                $this->assertSame($user->auth_version + 1, $user->fresh()->auth_version);
                $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $user->id)->count());
                $this->assertSame(401, $device->me()->status);
            }
        }
    }

    public function test_role_disable_and_login_both_orders_never_restore_old_authority(): void
    {
        $manager = $this->user('Fixture.RoleLoginAdmin');
        $a = $this->browser($this->server());
        $server = $this->server();
        $this->assertSame(200, $a->login($manager->username)->status);
        foreach ([true, false] as $loginFirst) {
            $suffix = $loginFirst ? 'First' : 'Second';
            $role = Role::create(['name' => 'Fixture Login '.$suffix, 'guard_name' => 'web']);
            $role->givePermissionTo('identity.users.view');
            $staff = $this->user('Fixture.RoleLogin.'.$suffix, $role->name);
            $old = $this->browser($a->server);
            $candidate = $this->browser($server);
            $this->assertSame(200, $old->login($staff->username)->status);
            $disable = $a->prepare('PUT', '/api/v1/roles/'.$role->id, $this->roleFields($role, false),
                $this->raceOptions($loginFirst ? 'observe' : 'hold-role-write', $loginFirst ? 'second' : 'first'));
            $login = $candidate->prepare('POST', '/api/v1/auth/login', ['username' => $staff->username, 'password' => 'test-fixture-only-password'],
                $this->raceOptions($loginFirst ? 'hold-marker' : 'observe', $loginFirst ? 'first' : 'second'));
            [$first, $second] = $loginFirst ? $this->race($login, $disable, 'manager') : $this->race($disable, $login, 'manager');
            $this->assertSame(200, $first->status);
            $this->assertSame($loginFirst ? 200 : 401, $second->status);
            $this->assertSame($staff->auth_version + 1, $staff->fresh()->auth_version);
            $this->assertTrue($staff->fresh()->is_active);
            $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $staff->id)->count());
            $this->assertSame(200, $a->request('PUT', '/api/v1/roles/'.$role->id, $this->roleFields($role, true))->status);
            $this->assertSame(401, $old->me()->status);
            $this->assertSame(401, $candidate->me()->status);
            $this->assertSame(200, $old->login($staff->username)->status);
        }
    }

    public function test_role_disable_and_profile_change_both_orders_recheck_generation_and_eligibility(): void
    {
        $manager = $this->user('Fixture.RoleProfileAdmin');
        $admin = $this->browser($this->server());
        $server = $this->server();
        $this->assertSame(200, $admin->login($manager->username)->status);
        foreach ([true, false] as $profileFirst) {
            $suffix = $profileFirst ? 'First' : 'Second';
            $role = Role::create(['name' => 'Fixture Profile '.$suffix, 'guard_name' => 'web']);
            $staff = $this->user('Fixture.RoleProfile.'.$suffix, $role->name);
            $profile = $this->browser($server);
            $this->assertSame(200, $profile->login($staff->username)->status);
            $disable = $admin->prepare('PUT', '/api/v1/roles/'.$role->id, $this->roleFields($role, false),
                $this->raceOptions($profileFirst ? 'observe' : 'hold-role-write', $profileFirst ? 'second' : 'first'));
            $change = $profile->prepare('PUT', '/api/v1/profile/password', [
                'currentPassword' => 'test-fixture-only-password', 'password' => 'synthetic-role-profile-race',
                'passwordConfirmation' => 'synthetic-role-profile-race',
            ], $this->raceOptions($profileFirst ? 'hold-password' : 'observe', $profileFirst ? 'first' : 'second'));
            [$first, $second] = $profileFirst ? $this->race($change, $disable, 'manager') : $this->race($disable, $change, 'manager');
            $this->assertSame(200, $first->status);
            // Existing Profile checks current eligibility before the binding.
            $this->assertSame($profileFirst ? 200 : 403, $second->status);
            $this->assertSame($staff->auth_version + ($profileFirst ? 2 : 1), $staff->fresh()->auth_version);
            $this->assertTrue(Hash::check($profileFirst ? 'synthetic-role-profile-race' : 'test-fixture-only-password', $staff->fresh()->password));
            $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $staff->id)->count());
            $this->assertSame(401, $profile->me()->status);
        }
    }

    public function test_late_native_insert_after_role_disable_is_anonymous_after_reenable(): void
    {
        $manager = $this->user('Fixture.RoleLateAdmin');
        $role = Role::create(['name' => 'Fixture Late Role', 'guard_name' => 'web']);
        $staff = $this->user('Fixture.RoleLateStaff', $role->name);
        $other = $this->user('Fixture.RoleLateOther', 'reception');
        $a = $this->browser($this->server());
        $admin = $this->browser($this->server());
        $unaffected = $this->browser($admin->server);
        $this->assertSame(200, $a->login($staff->username)->status);
        $this->assertSame(200, $admin->login($manager->username)->status);
        $this->assertSame(200, $unaffected->login($other->username)->status);
        $oldId = $a->sessionId();
        [$first, $second] = $this->race(
            $a->prepare('GET', '/api/v1/auth/me', options: $this->raceOptions('hold-late-insert', 'first')),
            $admin->prepare('PUT', '/api/v1/roles/'.$role->id, $this->roleFields($role, false), $this->raceOptions('observe', 'second')),
            null,
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(200, $second->status);
        $row = DB::table('sessions')->where('id', $oldId)->first();
        $this->assertNotNull($row, 'Actual native late INSERT must occur.');
        $this->assertNull($row->auth_user_id);
        $this->assertNull($row->auth_version);
        $this->assertNull($row->auth_last_activity);
        $this->assertSame($staff->auth_version + 1, $staff->fresh()->auth_version);
        $this->assertSame(200, $admin->request('PUT', '/api/v1/roles/'.$role->id, $this->roleFields($role, true))->status);
        $this->assertSame(401, $a->me()->status);
        $this->assertSame(200, $unaffected->me()->status);
        $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $staff->id)->count());
        $this->assertSame(200, $a->login($staff->username)->status);
    }

    public function test_revoked_role_grant_rejects_waiting_role_write_without_revoking_valid_session(): void
    {
        $manager = $this->user('Fixture.RoleGrantAdmin');
        $delegated = Role::create(['name' => 'Fixture Delegated', 'guard_name' => 'web']);
        $delegated->givePermissionTo(['identity.roles.update', 'identity.roles.show']);
        $actor = $this->user('Fixture.RoleGrantActor', $delegated->name);
        $target = Role::create(['name' => 'Fixture Grant Target', 'guard_name' => 'web']);
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $this->assertSame(200, $a->login($manager->username)->status);
        $this->assertSame(200, $b->login($actor->username)->status);
        $old = $b->sessionId();
        [$first, $second] = $this->race(
            $a->prepare('PUT', '/api/v1/roles/'.$delegated->id, [
                'name' => 'Fixture Delegated Renamed', 'isActive' => true, 'permissions' => ['identity.roles.show'],
            ], $this->raceOptions('hold-role-write', 'first')),
            $b->prepare('PUT', '/api/v1/roles/'.$target->id, [
                'name' => 'Must not commit', 'isActive' => true, 'permissions' => ['identity.users.delete'],
            ], $this->raceOptions('observe', 'second')),
            'manager',
        );
        $this->assertSame(200, $first->status);
        $this->assertSame(403, $second->status);
        $this->assertSame('rolled_back', $second->header('X-Test-Roles-Outcome'));
        $this->assertSame($target->name, $target->fresh()->name);
        $this->assertSame(0, $target->permissions()->count());
        $this->assertSame($old, $b->sessionId());
        $this->assertSame($actor->auth_version, $actor->fresh()->auth_version);
        $me = $b->me();
        $this->assertSame(200, $me->status);
        $this->assertSame(['identity.roles.show'], $me->json()['data']['permissions']);
    }

    public function test_role_disable_first_cannot_be_followed_by_removing_last_admin_user(): void
    {
        $this->lastAdminRace(true);
    }

    public function test_user_disable_first_cannot_be_followed_by_removing_last_admin_role(): void
    {
        $this->lastAdminRace(false);
    }

    private function lastAdminRace(bool $roleFirst): void
    {
        $this->user('Fixture.RoleRaceFounder', 'reception');
        $one = Role::create(['name' => 'Fixture Viable One', 'guard_name' => 'web']);
        $two = Role::create(['name' => 'Fixture Viable Two', 'guard_name' => 'web']);
        $operator = Role::create(['name' => 'Fixture Operator', 'guard_name' => 'web']);
        $one->syncPermissions(IdentityPermissions::ESSENTIAL);
        $two->syncPermissions(IdentityPermissions::ESSENTIAL);
        $operator->syncPermissions(['identity.roles.update', 'identity.users.update']);
        $firstAdmin = $this->user('Fixture.ViableOne', $one->name);
        $secondAdmin = $this->user('Fixture.ViableTwo', $two->name);
        $staff = $this->user('Fixture.Operator', $operator->name);
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $oneDevice = $this->browser($a->server);
        $twoDevice = $this->browser($b->server);
        $this->assertSame(200, $a->login($staff->username)->status);
        $this->assertSame(200, $b->login($staff->username)->status);
        $this->assertSame(200, $oneDevice->login($firstAdmin->username)->status);
        $this->assertSame(200, $twoDevice->login($secondAdmin->username)->status);
        $role = $a->prepare('PUT', '/api/v1/roles/'.$one->id, $this->roleFields($one, false),
            $this->raceOptions($roleFirst ? 'hold-role-write' : 'observe', $roleFirst ? 'first' : 'second'));
        $user = $b->prepare('PUT', '/api/v1/users/'.$secondAdmin->id, $this->fields($secondAdmin, $two->name, false),
            $this->raceOptions($roleFirst ? 'observe' : 'hold-users-update', $roleFirst ? 'second' : 'first'));
        [$first, $second] = $roleFirst ? $this->race($role, $user, 'manager') : $this->race($user, $role, 'manager');
        $this->assertSame(200, $first->status);
        $this->assertSame(409, $second->status);
        $this->assertSame('IDENTITY_LAST_ADMIN', $second->json()['errors']['code']);
        $this->assertSame('rolled_back', $second->header($roleFirst ? 'X-Test-Users-Outcome' : 'X-Test-Roles-Outcome'));
        $this->assertSame(! $roleFirst, $one->fresh()->is_active);
        $this->assertSame($roleFirst, $secondAdmin->fresh()->is_active);
        $this->assertSame($firstAdmin->auth_version + (int) $roleFirst, $firstAdmin->fresh()->auth_version);
        $this->assertSame($secondAdmin->auth_version + (int) ! $roleFirst, $secondAdmin->fresh()->auth_version);
        $this->assertSame($roleFirst ? 401 : 200, $oneDevice->me()->status);
        $this->assertSame($roleFirst ? 200 : 401, $twoDevice->me()->status);
        $this->assertSame(200, $a->me()->status);
        $this->assertSame(200, $b->me()->status);
    }

    private function roleFields(Role $role, bool $active): array
    {
        return ['name' => $role->name, 'isActive' => $active, 'permissions' => $role->permissions()->orderBy('permissions.name')->pluck('permissions.name')->all()];
    }
}
