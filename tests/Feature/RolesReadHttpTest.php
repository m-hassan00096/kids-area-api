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
use Modules\Identity\Models\User;

class RolesReadHttpTest extends AuthenticationHttpTestCase
{
    public function test_independent_read_capabilities_and_no_target_lookup_before_authorization(): void
    {
        $server = $this->server();
        $target = Role::findByName('manager', 'web');
        $other = Role::create(['name' => 'fixture-other-guard', 'guard_name' => 'other']);
        $missing = (int) $other->id + 10000;
        foreach (['index', 'show', 'store', 'update', 'delete'] as $capability) {
            $role = Role::create(['name' => 'fixture-read-'.$capability, 'guard_name' => 'web']);
            $role->givePermissionTo(Permission::findByName('identity.roles.'.$capability, 'web'));
            $actor = $this->user('Fixture.Read.'.$capability, $role->name);
            $browser = $this->browser($server);
            $this->assertSame(200, $browser->login($actor->username)->status);
            $response = $browser->request('GET', '/api/v1/roles');
            $this->assertSame($capability === 'index' ? 200 : 403, $response->status);
            $this->assertSame($capability === 'index' ? '4' : '0', $response->header('X-Test-Roles-Reads'));

            foreach ([$target->id, $other->id, $missing] as $id) {
                $response = $browser->request('GET', '/api/v1/roles/'.$id);
                $expected = $capability !== 'show' ? 403 : ($id === $target->id ? 200 : 404);
                $this->assertSame($expected, $response->status);
                $this->assertSame($capability === 'show' ? '1' : '0', $response->header('X-Test-Roles-Target-Reads'));
                $this->assertSame($expected === 200 ? '4' : ($capability === 'show' ? '1' : '0'), $response->header('X-Test-Roles-Reads'));
                $this->assertTrue(Str::isUuid($response->header('X-Request-ID')));
                if ($expected !== 200) {
                    $this->assertArrayNotHasKey('data', $response->json());
                }
            }
            $this->assertSame(200, $browser->me()->status);
        }
    }

    public function test_exact_resources_counts_literal_filters_and_bounded_snapshot_reads(): void
    {
        $manager = $this->user('Fixture.Roles.Manager');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($manager->username)->status);
        $role = Role::create(['name' => 'مشرف %_! الأنشطة', 'guard_name' => 'web', 'is_active' => false]);
        $ordinary = Role::create(['name' => 'مشرف XY الأنشطة', 'guard_name' => 'web']);
        $this->user('Fixture.Assigned.Active', $role->name);
        $this->user('Fixture.Assigned.Inactive', $role->name, ['is_active' => false]);
        $role->givePermissionTo(['identity.users.view', 'identity.roles.show']);
        DB::table('permissions')->where('name', 'identity.users.view')->where('guard_name', 'web')
            ->update(['alias_name' => 'عرض مخصص']);
        // A foreign morph or orphan is not an assigned existing User; an
        // unregistered/wrong-guard definition is not a catalogue permission.
        DB::table('model_has_roles')->insert([
            ['role_id' => $role->id, 'model_type' => 'FixtureForeignModel', 'model_id' => $manager->id],
            ['role_id' => $role->id, 'model_type' => (new User)->getMorphClass(), 'model_id' => 999999],
        ]);
        $unknown = DB::table('permissions')->insertGetId(['name' => 'identity.unknown', 'guard_name' => 'web', 'alias_name' => 'Unknown']);
        $wrong = DB::table('permissions')->insertGetId(['name' => 'identity.roles.show', 'guard_name' => 'other', 'alias_name' => 'Wrong']);
        DB::table('role_has_permissions')->insert([
            ['role_id' => $role->id, 'permission_id' => $unknown],
            ['role_id' => $role->id, 'permission_id' => $wrong],
        ]);
        Role::create(['name' => 'مشرف %_! خارجي', 'guard_name' => 'other']);

        $item = ['roleId' => (int) $role->id, 'name' => $role->name, 'usersCount' => 2, 'permissionsCount' => 2, 'isActive' => false, 'isProtected' => false];
        $response = $browser->request('GET', '/api/v1/roles/'.$role->id);
        $this->assertSame(200, $response->status);
        $this->assertSame(['success' => true, 'message' => '', 'data' => $item + ['permissions' => [
            ['name' => 'identity.roles.show', 'aliasName' => 'عرض تفاصيل الدور'],
            ['name' => 'identity.users.view', 'aliasName' => 'عرض مخصص'],
        ]]], $response->json());
        $this->assertSame('4', $response->header('X-Test-Roles-Reads'));
        $managerRole = Role::findByName('manager', 'web');
        $protected = $browser->request('GET', '/api/v1/roles/'.$managerRole->id)->json()['data'];
        $this->assertTrue($protected['isProtected']);
        $this->assertSame(10, $protected['permissionsCount']);
        $this->assertCount(10, $protected['permissions']);

        $response = $browser->request('GET', '/api/v1/roles?'.http_build_query(['filter' => ['search' => '%_!', 'isActive' => '0']]));
        $this->assertSame(200, $response->status);
        $this->assertSame(['success' => true, 'message' => '', 'data' => [
            'items' => [$item], 'pagination' => ['total' => 1, 'currentPage' => 1, 'perPage' => 15, 'lastPage' => 1],
        ]], $response->json());
        $this->assertSame('4', $response->header('X-Test-Roles-Reads'));
        $response = $browser->request('GET', '/api/v1/roles?'.http_build_query(['filter' => ['search' => 'مشرف']]));
        $this->assertSame([$ordinary->id, $role->id], array_column($response->json()['data']['items'], 'roleId'));

        $rows = [];
        for ($i = 1; $i <= 120; $i++) {
            $rows[] = ['name' => 'fixture-page-'.$i, 'guard_name' => 'web', 'is_active' => true];
        }
        DB::table('roles')->insert($rows);
        foreach ([['', 15, 1, 15], ['?perPage=500', 100, 1, 100], ['?perPage=100&page=2', 100, 2, 24]] as [$params, $size, $page, $count]) {
            $response = $browser->request('GET', '/api/v1/roles'.$params);
            $this->assertSame(200, $response->status);
            $data = $response->json()['data'];
            $this->assertSame(['total' => 124, 'currentPage' => $page, 'perPage' => $size, 'lastPage' => (int) ceil(124 / $size)], $data['pagination']);
            $this->assertCount($count, $data['items']);
            $this->assertSame(DB::table('roles')->whereRaw('BINARY guard_name = ?', ['web'])->orderByDesc('id')->forPage($page, $size)->pluck('id')->all(), array_column($data['items'], 'roleId'));
            $this->assertSame('4', $response->header('X-Test-Roles-Reads'));
        }
        foreach (['?page='.PHP_INT_MAX, '?filter[search]=no-fixture-match'] as $params) {
            $response = $browser->request('GET', '/api/v1/roles'.$params);
            $this->assertSame(200, $response->status);
            $this->assertSame([], $response->json()['data']['items']);
            $this->assertSame('1', $response->header('X-Test-Roles-Reads'));
        }
    }

    public function test_role_read_transport_and_malformed_queries(): void
    {
        $server = $this->server();
        $browser = $this->browser($server);
        foreach (['/api/v1/roles', '/api/v1/roles/1'] as $path) {
            $this->assertSame(401, $browser->request('GET', $path)->status);
            foreach ([
                ['Origin' => 'https://unapproved.invalid'], ['Origin' => null, 'Referer' => null],
                ['Authorization' => 'Bearer synthetic-token'], ['Host' => 'unapproved.invalid'],
            ] as $headers) {
                $this->assertSame(403, $browser->request('GET', $path, options: ['headers' => $headers])->status);
            }
        }
        $actor = $this->user('Fixture.Roles.Boundary');
        $this->assertSame(200, $browser->login($actor->username)->status);
        foreach (['?sort=name', '?include=permissions', '?filter[roleId]=1', '?filter=x', '?filter[search][]=x',
            '?filter[isActive]=true', '?filter[isActive]=2', '?filter[isActive][]=1', '?page=0', '?page=9223372036854775808',
            '?perPage=-1', '?perPage[]=15', '?filter[search]='.str_repeat('x', 256)] as $query) {
            $response = $browser->request('GET', '/api/v1/roles'.$query);
            $this->assertSame(422, $response->status, $query);
            $this->assertSame('0', $response->header('X-Test-Roles-Reads'));
        }
        $this->assertSame(422, $browser->request('GET', '/api/v1/roles/1?include=permissions')->status);
        $this->assertSame(422, $browser->request('GET', '/api/v1/roles', options: ['raw' => '{}'])->status);
        foreach (['0', '-1', 'abc', '01', '9223372036854775808'] as $id) {
            $this->assertSame(404, $browser->request('GET', '/api/v1/roles/'.$id)->status);
        }
        $this->assertSame(422, $browser->request('POST', '/api/v1/roles/1?_method=PUT', [])->status);
        $this->assertSame(422, $browser->request('POST', '/api/v1/roles/1', [], ['headers' => ['X-HTTP-Method-Override' => 'PUT']])->status);
        $this->assertSame(200, $browser->me()->status);

        $expected = ['api/v1/roles' => 'identity.roles.index', 'api/v1/roles/{role}' => 'identity.roles.show'];
        foreach (Route::getRoutes() as $route) {
            if (! isset($expected[$route->uri()]) || ! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            $this->assertContains(RequireStatefulBrowser::class, $middleware);
            $this->assertContains('auth:sanctum', $middleware);
            $this->assertContains(RequireStaffSession::class, $middleware);
            $this->assertContains(RequireRolesCapability::class.':'.$expected[$route->uri()], $middleware);
            unset($expected[$route->uri()]);
        }
        $this->assertSame([], $expected);
    }
}
