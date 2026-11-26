<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Support\IdentityPermissions;
use Tests\Support\AuthenticationRaceHarness;

class RolesActualFaultTest extends AuthenticationHttpTestCase
{
    use AuthenticationRaceHarness;

    public function test_actual_role_grant_generation_session_and_delete_faults_rollback_all_rows(): void
    {
        $manager = $this->user('Fixture.RoleFaultAdmin');
        $role = Role::create(['name' => 'Fixture Fault Role', 'guard_name' => 'web']);
        $role->givePermissionTo('identity.users.view');
        $active = $this->user('Fixture.RoleFaultActive', $role->name);
        $inactive = $this->user('Fixture.RoleFaultInactive', $role->name, ['is_active' => false]);
        $server = $this->server();
        $admin = $this->browser($server);
        $a = $this->browser($server);
        $b = $this->browser($server);
        $this->assertSame(200, $admin->login($manager->username)->status);
        $this->assertSame(200, $a->login($active->username)->status);
        $this->assertSame(200, $b->login($active->username)->status);
        $ids = [$active->id, $inactive->id];
        $before = $this->snapshot($role, $ids);
        $body = ['name' => 'Must not commit', 'isActive' => false, 'permissions' => ['identity.roles.show']];
        foreach (['fault-role-write', 'fault-role-grant', 'fault-role-generation', 'fault-role-session'] as $mode) {
            $result = $admin->request('PUT', '/api/v1/roles/'.$role->id, $body, $this->inspect($mode));
            $this->assertSame(500, $result->status, $mode.' '.($result->header('X-Test-Exception-Class') ?? ''));
            $this->assertSame(['success' => false, 'message' => __('api.errors.internal'), 'errors' => []], $result->json());
            $this->assertSame('rolled_back', $result->header('X-Test-Roles-Outcome'));
            $this->assertSame('1', $result->header('X-Test-Roles-Outcome-Reported'));
            $this->assertSame('1', $result->header('X-Test-Redacted'));
            $this->assertSame($before, $this->snapshot($role, $ids));
        }
        $this->assertSame(200, $a->me()->status);
        $this->assertSame(200, $b->me()->status);

        $result = $admin->request('POST', '/api/v1/roles', [
            'name' => 'Fixture Grant Fault', 'permissions' => ['identity.roles.show'],
        ], $this->inspect('fault-role-grant'));
        $this->assertSame(500, $result->status);
        $this->assertSame('rolled_back', $result->header('X-Test-Roles-Outcome'));
        $this->assertSame('1', $result->header('X-Test-Redacted'));
        $this->assertFalse(Role::where('name', 'Fixture Grant Fault')->exists());

        $unused = Role::create(['name' => 'Fixture Unused Fault', 'guard_name' => 'web']);
        $unused->givePermissionTo('identity.roles.show');
        $before = $this->snapshot($unused, []);
        $result = $admin->request('DELETE', '/api/v1/roles/'.$unused->id, options: $this->inspect('fault-role-delete'));
        $this->assertSame(500, $result->status);
        $this->assertSame('rolled_back', $result->header('X-Test-Roles-Outcome'));
        $this->assertSame('1', $result->header('X-Test-Redacted'));
        $this->assertSame($before, $this->snapshot($unused, []));
        $this->assertSame(200, $admin->me()->status);
    }

    public function test_native_duplicate_constraint_and_lock_timeout_preserve_data_without_retry(): void
    {
        $manager = $this->user('Fixture.RoleConstraintAdmin');
        $admin = $this->browser($this->server());
        $this->assertSame(200, $admin->login($manager->username)->status);
        $count = Role::count();
        $response = $admin->request('POST', '/api/v1/roles', [
            'name' => 'Fixture Constraint Collision', 'permissions' => ['identity.roles.show'],
        ], $this->inspect('roles-action-duplicate'));
        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('name', $response->json()['errors']);
        $this->assertSame('1062', $response->header('X-Test-Roles-MySQL-Code'));
        $this->assertSame('1', $response->header('X-Test-Roles-MySQL-Count'));
        $this->assertSame('rolled_back', $response->header('X-Test-Roles-Outcome'));
        $this->assertSame('1', $response->header('X-Test-Redacted'));
        $this->assertSame($count, Role::count());

        $role = Role::create(['name' => 'Fixture Timeout', 'guard_name' => 'web']);
        $before = $this->snapshot($role, []);
        DB::beginTransaction();
        try {
            DB::table('roles')->where('name', 'manager')->where('guard_name', 'web')->lockForUpdate()->first(['id']);
            $start = microtime(true);
            $response = $admin->request('PUT', '/api/v1/roles/'.$role->id, [
                'name' => 'Must not commit', 'isActive' => false, 'permissions' => [],
            ], $this->inspect('roles-action-timeout-restore'));
            $elapsed = microtime(true) - $start;
            $this->assertGreaterThanOrEqual(4.5, $elapsed);
            $this->assertLessThan(20, $elapsed);
            $this->assertSame(503, $response->status);
            $this->assertSame('1205', $response->header('X-Test-Roles-MySQL-Code'));
            $this->assertSame('1', $response->header('X-Test-Roles-MySQL-Count'));
            $this->assertSame('rolled_back', $response->header('X-Test-Roles-Outcome'));
            $this->assertSame('1', $response->header('X-Test-Roles-Restore-Warning'));
            $this->assertSame('1', $response->header('X-Test-Redacted'));
        } finally {
            DB::rollBack();
        }
        $this->assertSame($before, $this->snapshot($role, []));
        $this->assertSame(200, $admin->me()->status);
    }

    public function test_actual_innodb_deadlock_aborts_role_attempt_once_without_mutation(): void
    {
        $manager = $this->user('Fixture.RoleDeadlockAdmin');
        $role = Role::create(['name' => 'Fixture Deadlock', 'guard_name' => 'web']);
        $target = $this->user('Fixture.RoleDeadlockTarget', $role->name);
        $admin = $this->browser($this->server());
        $external = $this->browser($this->server());
        $this->assertSame(200, $admin->login($manager->username)->status);
        $before = $this->snapshot($role, [$target->id]);
        [$first, $second] = $this->race(
            $external->prepare('POST', '/api/v1/__fixture-roles-deadlock/'.$target->id, options: ['headers' => [
                'X-Identity-Test-Mode' => 'hold-roles-deadlock', 'X-Identity-Test-Barrier' => 'first',
            ]]),
            $admin->prepare('PUT', '/api/v1/roles/'.$role->id, [
                'name' => 'Must not commit', 'isActive' => false, 'permissions' => [],
            ], $this->inspect('roles-action-deadlock', 'second')),
            'users',
        );
        $this->assertSame(201, $first->status);
        $this->assertSame(503, $second->status, $second->header('X-Test-Exception-Class') ?? '');
        $this->assertSame('1213', $second->header('X-Test-Roles-MySQL-Code'));
        $this->assertSame('1', $second->header('X-Test-Roles-MySQL-Count'));
        $this->assertSame('rolled_back', $second->header('X-Test-Roles-Outcome'));
        $this->assertSame('1', $second->header('X-Test-Redacted'));
        $this->assertSame(100, DB::table('identity_login_throttles')->where('scope', 250)->count());
        $this->assertSame($before, $this->snapshot($role, [$target->id]));
        $this->assertSame(200, $admin->me()->status);
    }

    public function test_lost_commit_acknowledgement_and_cache_failure_do_not_replay_role_disable(): void
    {
        $manager = $this->user('Fixture.RoleCommitAdmin');
        $admin = $this->browser($this->server());
        $server = $this->server();
        $this->assertSame(200, $admin->login($manager->username)->status);
        foreach (['roles-action-cache' => 200, 'roles-action-commit-ack' => 503] as $mode => $status) {
            $role = Role::create(['name' => 'Fixture '.$mode, 'guard_name' => 'web']);
            $role->givePermissionTo('identity.users.view');
            $user = $this->user('Fixture.'.$mode, $role->name);
            $device = $this->browser($server);
            $this->assertSame(200, $device->login($user->username)->status);
            $body = ['name' => 'Updated '.$mode, 'isActive' => false, 'permissions' => ['identity.roles.show']];
            $result = $admin->request('PUT', '/api/v1/roles/'.$role->id, $body, $this->inspect($mode));
            $this->assertSame($status, $result->status, $result->header('X-Test-Exception-Class') ?? '');
            $this->assertSame($status === 503 ? 'unknown' : 'committed', $result->header('X-Test-Roles-Outcome'));
            $this->assertSame('1', $result->header('X-Test-Redacted'));
            if ($status === 503) {
                $this->assertSame('1', $result->header('X-Test-Actual-Commit-Count'));
                $this->assertSame('1', $result->header('X-Test-Roles-Outcome-Reported'));
            } else {
                $this->assertSame('1', $result->header('X-Test-Roles-Cache-Warning'));
            }
            $this->assertSame($body['name'], $role->fresh()->name);
            $this->assertFalse($role->fresh()->is_active);
            $this->assertSame(['identity.roles.show'], $role->permissions()->pluck('permissions.name')->all());
            $this->assertSame($user->auth_version + 1, $user->fresh()->auth_version);
            $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $user->id)->count());
            $this->assertSame(401, $device->me()->status);
            $this->assertSame(200, $admin->me()->status);
        }
    }

    public function test_self_disable_native_session_save_failure_preserves_known_commit(): void
    {
        $manager = $this->user('Fixture.RoleNativeSaveAdmin');
        $role = Role::create(['name' => 'Fixture Self Fault', 'guard_name' => 'web']);
        $role->syncPermissions(IdentityPermissions::ESSENTIAL);
        $actor = $this->user('Fixture.RoleNativeSaveActor', $role->name);
        $server = $this->server();
        $admin = $this->browser($server);
        $a = $this->browser($server);
        $b = $this->browser($server);
        $this->assertSame(200, $admin->login($manager->username)->status);
        $this->assertSame(200, $a->login($actor->username)->status);
        $this->assertSame(200, $b->login($actor->username)->status);
        $response = $a->request('PUT', '/api/v1/roles/'.$role->id, [
            'name' => $role->name, 'isActive' => false, 'permissions' => IdentityPermissions::ESSENTIAL,
        ], $this->inspect('roles-action-final-save'));
        $this->assertSame(500, $response->status);
        $this->assertSame('committed', $response->header('X-Test-Roles-Outcome'));
        $this->assertSame('1', $response->header('X-Test-Roles-Outcome-Reported'));
        $this->assertSame('1', $response->header('X-Test-Redacted'));
        $this->assertSame('1', $response->header('X-Test-Users-Self-Revoked'));
        $this->assertSame('0', $response->header('X-Test-Users-Touches'));
        $this->assertFalse($role->fresh()->is_active);
        $this->assertSame($actor->auth_version + 1, $actor->fresh()->auth_version);
        $this->assertSame(0, DB::table('sessions')->where('auth_user_id', $actor->id)->count());
        $this->assertSame(401, $a->me()->status);
        $this->assertSame(401, $b->me()->status);
        $this->assertSame(200, $admin->me()->status);
    }

    private function snapshot(Role $role, array $users): array
    {
        return [
            'role' => $role->fresh()?->getRawOriginal(),
            'grants' => DB::table('role_has_permissions')->where('role_id', $role->id)->orderBy('permission_id')->get()->toJson(),
            'members' => DB::table('model_has_roles')->where('role_id', $role->id)->orderBy('model_type')->orderBy('model_id')->get()->toJson(),
            'users' => User::query()->whereIn('id', $users)->orderBy('id')->get()->map(fn (User $user) => $user->getRawOriginal())->all(),
            'sessions' => DB::table('sessions')->whereIn('auth_user_id', $users)->orderBy('id')->get()->toJson(),
        ];
    }

    private function inspect(string $mode, ?string $barrier = null): array
    {
        $headers = ['X-Identity-Test-Mode' => $mode, 'X-Identity-Test-Inspect' => '1'];
        if ($barrier !== null) {
            $headers['X-Identity-Test-Barrier'] = $barrier;
        }

        return ['headers' => $headers];
    }
}
