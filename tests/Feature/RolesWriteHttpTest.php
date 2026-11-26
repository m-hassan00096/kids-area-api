<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\Identity\Http\Middleware\RequireRolesCapability;
use Modules\Identity\Http\Middleware\RequireStaffSession;
use Modules\Identity\Http\Middleware\RequireStatefulBrowser;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Support\IdentityPermissions;

class RolesWriteHttpTest extends AuthenticationHttpTestCase
{
    public function test_actual_create_rename_replace_grants_and_delete_without_direct_assignments(): void
    {
        $actor = $this->user('Fixture.RoleAdmin');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        $beforePermissions = DB::table('permissions')->orderBy('id')->get()->toJson();
        $beforeAssignments = DB::table('model_has_roles')->orderBy('role_id')->orderBy('model_id')->get()->toJson();

        $create = $browser->request('POST', '/api/v1/roles', ['name' => '  مشرف الأنشطة  ', 'permissions' => []]);
        $this->assertSame(201, $create->status, $create->header('X-Test-Exception-Class') ?? '');
        $this->assertSame(['success' => true, 'message' => __('identity.role_created'), 'data' => []], $create->json());
        $role = Role::findByName('مشرف الأنشطة', 'web');
        $this->assertTrue($role->is_active);
        $this->assertSame(0, $role->permissions()->count());
        $id = $role->id;
        $path = '/api/v1/roles/'.$id;

        $update = $browser->request('PUT', $path, [
            'name' => 'Activities Supervisor', 'isActive' => true,
            'permissions' => ['identity.users.view', 'identity.audit.view'],
        ]);
        $this->assertSame(200, $update->status, $update->header('X-Test-Exception-Class') ?? '');
        $this->assertSame(['success' => true, 'message' => __('identity.role_updated'), 'data' => []], $update->json());
        $this->assertSame($id, $role->fresh()->id);
        $this->assertSame('Activities Supervisor', $role->fresh()->name);
        $this->assertSame(['identity.audit.view', 'identity.users.view'], $this->grants($role));
        $this->assertSame(200, $browser->request('PUT', $path, [
            'name' => 'Activities Supervisor', 'isActive' => true, 'permissions' => ['identity.roles.show'],
        ])->status);
        $this->assertSame(['identity.roles.show'], $this->grants($role));

        $deleted = $browser->request('DELETE', $path);
        $this->assertSame(200, $deleted->status, $deleted->header('X-Test-Exception-Class') ?? '');
        $this->assertSame(['success' => true, 'message' => __('identity.role_deleted'), 'data' => []], $deleted->json());
        $this->assertNull($role->fresh());
        $this->assertFalse(DB::table('role_has_permissions')->where('role_id', $id)->exists());
        $this->assertSame($beforePermissions, DB::table('permissions')->orderBy('id')->get()->toJson());
        $this->assertSame($beforeAssignments, DB::table('model_has_roles')->orderBy('role_id')->orderBy('model_id')->get()->toJson());
        $this->assertSame(0, DB::table('model_has_permissions')->count());
        $this->assertSame(404, $browser->request('DELETE', $path)->status);
        $this->assertSame(200, $browser->me()->status);
    }

    public function test_strict_json_name_and_grant_validation_and_actual_collation(): void
    {
        $actor = $this->user('Fixture.RoleValidation');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        $valid = ['name' => 'Fixture New Role', 'permissions' => []];
        foreach ([
            ['name', ''], ['name', '   '], ['name', str_repeat('x', 256)], ['name', 123],
            ['name', "name\ncontrol"], ['name', "name\u{202E}"], ['isActive', 'true'], ['isActive', 1],
            ['permissions', null], ['permissions', 'identity.users.view'],
            ['permissions', ['identity.users.view', 'identity.users.view']],
            ['permissions', ['IDENTITY.USERS.VIEW']], ['permissions', ['identity.unknown']],
            ['permissions', [123]], ['permissions', [['name' => 'identity.users.view']]],
            ['name', 'manager'], ['name', 'MANAGER'], ['name', 'mánaGer'], ['name', 'reception '],
        ] as [$field, $value]) {
            $response = $browser->request('POST', '/api/v1/roles', array_replace($valid, [$field => $value]));
            $this->assertSame(422, $response->status, $field.' '.($response->header('X-Test-Exception-Class') ?? ''));
            $this->assertArrayHasKey($field, $response->json()['errors']);
            $this->assertTrue(Str::isUuid($response->header('X-Request-ID')));
        }
        foreach (['aliasName', 'alias_name', 'guardName', 'guard_name', 'id', 'roleId', 'directUserGrants', '_method'] as $field) {
            $response = $browser->request('POST', '/api/v1/roles', $valid + [$field => 'injected']);
            $this->assertSame(422, $response->status);
        }
        foreach (['{"name":"Empty object","permissions":{}}', '{"name":"Numeric object","permissions":{"0":"identity.users.view"}}',
            '{"name":"Missing"}', '[]', 'null'] as $raw) {
            $this->assertSame(422, $browser->request('POST', '/api/v1/roles', options: ['raw' => $raw])->status);
        }
        $this->assertSame(400, $browser->request('POST', '/api/v1/roles', options: ['raw' => '{invalid'])->status);
        $this->assertSame(422, $browser->request('POST', '/api/v1/roles?name=query', $valid)->status);
        $this->assertSame(422, $browser->request('POST', '/api/v1/roles', options: [
            'raw' => 'name=Form&permissions[]=', 'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        ])->status);

        DB::table('permissions')->where('name', 'identity.audit.view')->update(['name' => 'IDENTITY.AUDIT.VIEW']);
        $this->assertSame(422, $browser->request('POST', '/api/v1/roles', [
            'name' => 'Missing definition', 'permissions' => ['identity.audit.view'],
        ])->status);
        $this->assertSame(2, Role::count());
        $this->assertSame(201, $browser->request('POST', '/api/v1/roles', $valid + ['isActive' => false])->status);
        $role = Role::findByName($valid['name'], 'web');
        $this->assertFalse($role->is_active);
        $this->assertSame(422, $browser->request('POST', '/api/v1/roles', [
            'name' => 'fixture new role', 'permissions' => [],
        ])->status);
        $this->assertSame(422, $browser->request('PUT', '/api/v1/roles/'.$role->id, $valid)->status);
        $this->assertSame(422, $browser->request('PUT', '/api/v1/roles/'.$role->id, [
            'name' => 'MANAGER', 'isActive' => false, 'permissions' => [],
        ])->status);
        $this->assertSame(3, Role::count());
        $this->assertSame(200, $browser->me()->status);
    }

    public function test_permanent_roles_assigned_roles_and_last_admin_are_protected(): void
    {
        $actor = $this->user('Fixture.RoleProtector');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        $manager = Role::findByName('manager', 'web');
        $reception = Role::findByName('reception', 'web');
        foreach ([$manager, $reception] as $role) {
            $path = '/api/v1/roles/'.$role->id;
            $grants = $this->grants($role);
            foreach ([
                ['name' => 'Renamed', 'isActive' => true, 'permissions' => $grants],
                ['name' => $role->name, 'isActive' => false, 'permissions' => $grants],
            ] as $body) {
                $response = $browser->request('PUT', $path, $body);
                $this->assertSame(409, $response->status);
                $this->assertSame('IDENTITY_ROLE_PROTECTED', $response->json()['errors']['code']);
            }
            $this->assertSame(409, $browser->request('DELETE', $path)->status);
            $this->assertSame($grants, $this->grants($role));
            $this->assertTrue($role->fresh()->is_active);
        }
        $original = $this->grants($manager);
        $response = $browser->request('PUT', '/api/v1/roles/'.$manager->id, [
            'name' => 'manager', 'isActive' => true, 'permissions' => array_values(array_diff($original, ['identity.users.view'])),
        ]);
        $this->assertSame(409, $response->status);
        $this->assertSame('IDENTITY_LAST_ADMIN', $response->json()['errors']['code']);
        $this->assertSame($original, $this->grants($manager));

        $used = Role::create(['name' => 'Fixture Used', 'guard_name' => 'web']);
        $inactive = $this->user('Fixture.RoleInactive', $used->name, ['is_active' => false]);
        $response = $browser->request('DELETE', '/api/v1/roles/'.$used->id);
        $this->assertSame(409, $response->status);
        $this->assertSame('IDENTITY_ROLE_ASSIGNED', $response->json()['errors']['code']);
        $this->assertSame([$used->id], $inactive->fresh()->roles()->pluck('roles.id')->all());

        $anomaly = Role::create(['name' => 'Fixture Anomaly', 'guard_name' => 'web']);
        $unknown = DB::table('permissions')->insertGetId(['name' => 'fixture.unknown', 'guard_name' => 'web', 'alias_name' => 'Unknown']);
        DB::table('role_has_permissions')->insert(['role_id' => $anomaly->id, 'permission_id' => $unknown]);
        $this->assertSame(409, $browser->request('PUT', '/api/v1/roles/'.$anomaly->id, [
            'name' => $anomaly->name, 'isActive' => true, 'permissions' => [],
        ])->status);
        $this->assertSame(409, $browser->request('DELETE', '/api/v1/roles/'.$anomaly->id)->status);
        $this->assertTrue(DB::table('role_has_permissions')->where('role_id', $anomaly->id)->where('permission_id', $unknown)->exists());
        $this->assertSame(200, $browser->me()->status);
    }

    public function test_independent_write_capabilities_native_csrf_and_preflight(): void
    {
        $this->user('Fixture.RoleFallbackAdmin');
        $server = $this->server();
        foreach (['index', 'show', 'store', 'update', 'delete'] as $capability) {
            $role = Role::create(['name' => 'fixture-write-'.$capability, 'guard_name' => 'web']);
            $role->givePermissionTo(Permission::findByName('identity.roles.'.$capability, 'web'));
            $actor = $this->user('Fixture.RoleWrite.'.$capability, $role->name);
            $target = Role::create(['name' => 'fixture-target-'.$capability, 'guard_name' => 'web']);
            $browser = $this->browser($server);
            $this->assertSame(200, $browser->login($actor->username)->status);
            $path = '/api/v1/roles/'.$target->id;
            foreach ([
                ['store', 'POST', '/api/v1/roles', ['name' => 'fixture-created-'.$capability, 'permissions' => ['identity.users.delete']]],
                ['update', 'PUT', $path, ['name' => $target->name, 'isActive' => true, 'permissions' => ['identity.users.delete']]],
                ['delete', 'DELETE', $path, null],
            ] as [$required, $method, $uri, $body]) {
                foreach (['missing', 'bad'] as $csrf) {
                    $this->assertSame(419, $browser->request($method, $uri, $body, ['csrf' => $csrf])->status);
                }
                $response = $browser->request($method, $uri, $body);
                $this->assertSame($capability === $required ? ($method === 'POST' ? 201 : 200) : 403,
                    $response->status, $capability.' '.$method.' '.($response->header('X-Test-Exception-Class') ?? ''));
                if ($capability !== $required) {
                    $this->assertSame('0', $response->header('X-Test-Roles-Target-Reads'));
                }
            }
            if ($capability === 'store') {
                $created = Role::findByName('fixture-created-store', 'web');
                $this->assertSame(['identity.users.delete'], $this->grants($created));
                $this->assertSame(['identity.roles.store'], $browser->me()->json()['data']['permissions']);
            }
            if ($capability === 'update') {
                $this->assertSame(['identity.users.delete'], $this->grants($target));
            }
            $this->assertSame(200, $browser->me()->status);
        }

        $expected = ['GET api/v1/roles' => 'index', 'GET api/v1/roles/{role}' => 'show',
            'POST api/v1/roles' => 'store', 'PUT api/v1/roles/{role}' => 'update', 'DELETE api/v1/roles/{role}' => 'delete'];
        foreach (Route::getRoutes() as $route) {
            $key = $route->methods()[0].' '.$route->uri();
            if (! isset($expected[$key])) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            foreach ([RequireStatefulBrowser::class, 'auth:sanctum', RequireStaffSession::class,
                RequireRolesCapability::class.':identity.roles.'.$expected[$key]] as $required) {
                $this->assertContains($required, $middleware);
            }
            unset($expected[$key]);
        }
        $this->assertSame([], $expected);

        $split = $this->server(splitOrigins: true);
        $guest = $this->browser($split, false);
        $before = DB::table('sessions')->count();
        $preflight = $guest->request('OPTIONS', '/api/v1/roles/1', options: ['headers' => [
            'Origin' => 'https://staff.kids.example.test:443', 'Access-Control-Request-Method' => 'DELETE',
            'Access-Control-Request-Headers' => 'X-XSRF-TOKEN,Content-Type',
        ]]);
        $this->assertSame(204, $preflight->status);
        $this->assertSame($split->origin, $preflight->header('Access-Control-Allow-Origin'));
        $this->assertSame('true', $preflight->header('Access-Control-Allow-Credentials'));
        $this->assertContains('DELETE', array_map('trim', explode(',', (string) $preflight->header('Access-Control-Allow-Methods'))));
        $this->assertSame($before, DB::table('sessions')->count());
        $this->assertSame(403, $guest->request('OPTIONS', '/api/v1/roles/1', options: ['headers' => [
            'Origin' => 'https://unapproved.invalid', 'Access-Control-Request-Method' => 'DELETE',
        ]])->status);
    }

    public function test_disable_revokes_all_assigned_generations_once_and_reenable_does_not_restore_sessions(): void
    {
        $manager = $this->user('Fixture.RoleDisableAdmin');
        $role = Role::create(['name' => 'Fixture Disable', 'guard_name' => 'web']);
        $role->givePermissionTo(['identity.users.view']);
        $staff = $this->user('Fixture.RoleDisableStaff', $role->name);
        $inactive = $this->user('Fixture.RoleDisableInactive', $role->name, ['is_active' => false]);
        $server = $this->server();
        $admin = $this->browser($server);
        $first = $this->browser($server);
        $second = $this->browser($server);
        $this->assertSame(200, $admin->login($manager->username)->status);
        $this->assertSame(200, $first->login($staff->username)->status);
        $this->assertSame(200, $second->login($staff->username)->status);
        $oldSessions = [$first->sessionId(), $second->sessionId()];
        $path = '/api/v1/roles/'.$role->id;
        $body = ['name' => 'Fixture Disable Renamed', 'isActive' => true, 'permissions' => ['identity.roles.show']];
        $this->assertSame(200, $admin->request('PUT', $path, $body)->status);
        $this->assertSame($staff->auth_version, $staff->fresh()->auth_version);
        $this->assertSame(['identity.roles.show'], $first->me()->json()['data']['permissions']);
        $this->assertSame('Fixture Disable Renamed', $second->me()->json()['data']['role']['name']);
        $body['isActive'] = false;
        $this->assertSame(200, $admin->request('PUT', $path, $body)->status);
        $this->assertSame($staff->auth_version + 1, $staff->fresh()->auth_version);
        $this->assertSame($inactive->auth_version + 1, $inactive->fresh()->auth_version);
        $this->assertTrue($staff->fresh()->is_active);
        $this->assertFalse($inactive->fresh()->is_active);
        $this->assertSame([$role->id], $staff->fresh()->roles()->pluck('roles.id')->all());
        $this->assertFalse(DB::table('sessions')->whereIn('id', $oldSessions)->whereNotNull('auth_user_id')->exists());
        $this->assertSame(200, $admin->request('PUT', $path, $body)->status);
        $body['isActive'] = true;
        $this->assertSame(200, $admin->request('PUT', $path, $body)->status);
        $this->assertSame($staff->auth_version + 1, $staff->fresh()->auth_version);
        $this->assertSame($inactive->auth_version + 1, $inactive->fresh()->auth_version);
        $this->assertSame(401, $first->me()->status);
        $this->assertSame(401, $second->me()->status);
        $new = $this->browser($server);
        $this->assertSame(200, $new->login($staff->username)->status);
        $this->assertSame(200, $admin->me()->status);
        $this->assertSame($manager->auth_version, $manager->fresh()->auth_version);
    }

    public function test_self_role_disable_rolls_back_if_last_admin_and_revokes_after_alternate_path_exists(): void
    {
        $role = Role::create(['name' => 'Fixture Self Admin', 'guard_name' => 'web']);
        $role->syncPermissions(IdentityPermissions::ESSENTIAL);
        $actor = $this->user('Fixture.SelfRole', $role->name);
        $server = $this->server();
        $browser = $this->browser($server);
        $this->assertSame(200, $browser->login($actor->username)->status);
        $path = '/api/v1/roles/'.$role->id;
        $body = ['name' => $role->name, 'isActive' => false, 'permissions' => IdentityPermissions::ESSENTIAL];
        $response = $browser->request('PUT', $path, $body);
        $this->assertSame(409, $response->status);
        $this->assertSame('IDENTITY_LAST_ADMIN', $response->json()['errors']['code']);
        $this->assertTrue($role->fresh()->is_active);
        $this->assertSame($actor->auth_version, $actor->fresh()->auth_version);
        $this->assertSame(200, $browser->me()->status);

        $this->user('Fixture.AlternateAdmin');
        $response = $browser->request('PUT', $path, $body);
        $this->assertSame(200, $response->status, $response->header('X-Test-Exception-Class') ?? '');
        $this->assertFalse($role->fresh()->is_active);
        $this->assertSame($actor->auth_version + 1, $actor->fresh()->auth_version);
        $this->assertSame(401, $browser->me()->status);
        $this->assertFalse(DB::table('sessions')->where('auth_user_id', $actor->id)->exists());
    }

    private function grants(Role $role): array
    {
        return $role->permissions()->orderBy('permissions.name')->pluck('permissions.name')->all();
    }
}
