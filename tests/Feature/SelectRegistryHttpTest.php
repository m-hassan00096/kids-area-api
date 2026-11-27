<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Support\PermissionCatalogue;
use Tests\Support\AuthenticationBrowser;
use Tests\Support\AuthenticationHttpResponse;

class SelectRegistryHttpTest extends AuthenticationHttpTestCase
{
    public function test_independent_capabilities_and_all_or_nothing_dispatch(): void
    {
        $server = $this->server();
        $inactive = Role::create(['name' => 'دور غير نشط', 'guard_name' => 'web', 'is_active' => false]);
        Role::create(['name' => 'fixture-other', 'guard_name' => 'other']);
        foreach (['create', 'update', 'view', 'delete'] as $capability) {
            $name = 'fixture-select-'.$capability;
            $role = Role::create(['name' => $name, 'guard_name' => 'web']);
            $role->givePermissionTo(Permission::findByName('identity.users.'.$capability, 'web'));
            $actor = $this->user('Fixture.Select.'.$capability, $name);
            $browser = $this->browser($server);
            $this->assertSame(200, $browser->login($actor->username)->status);
            foreach (['roles', 'userFilterRoles'] as $key) {
                $allowed = $key === 'roles' ? in_array($capability, ['create', 'update'], true) : $capability === 'view';
                $response = $this->select($browser, $key);
                $this->assertSame($allowed ? 200 : 403, $response->status, $capability.' '.$key);
                $this->assertSame($allowed ? '1' : '0', $response->header('X-Test-Select-Reads'));
                if ($allowed) {
                    $data = $response->json()['data'];
                    $this->assertSame([['label' => $key, 'options' => $this->expected($key === 'roles')]], $data);
                    $this->assertSame($key === 'userFilterRoles', in_array($inactive->id, array_column($data[0]['options'], 'value'), true));
                } else {
                    $this->assertArrayNotHasKey('data', $response->json());
                }
            }
            foreach (['roles,userFilterRoles', 'userFilterRoles,roles'] as $keys) {
                $response = $this->select($browser, $keys);
                $this->assertSame(403, $response->status);
                $this->assertSame('0', $response->header('X-Test-Select-Reads'));
                $this->assertArrayNotHasKey('data', $response->json());
            }
            $this->assertSame(200, $browser->me()->status);
        }
        $manager = $this->user('Fixture.Manager');
        $browser = $this->browser($server);
        $this->assertSame(200, $browser->login($manager->username)->status);
        foreach ([['roles', 'userFilterRoles'], ['userFilterRoles', 'roles']] as $keys) {
            $response = $this->select($browser, implode(',', $keys));
            $this->assertSame(200, $response->status);
            $this->assertSame(['success' => true, 'message' => '', 'data' => array_map(fn ($key) => ['label' => $key, 'options' => $this->expected($key === 'roles')], $keys)], $response->json());
            $this->assertSame('2', $response->header('X-Test-Select-Reads'));
            $this->assertTrue(Str::isUuid($response->header('X-Request-ID')));
        }
    }

    public function test_permission_choices_use_exact_catalogue_and_independent_delegation_permissions(): void
    {
        $server = $this->server();
        DB::table('permissions')->where('name', 'identity.users.view')->where('guard_name', 'web')
            ->update(['alias_name' => 'اسم صلاحية مخصص']);
        DB::table('permissions')->insert([
            ['name' => 'identity.users.view', 'guard_name' => 'other', 'alias_name' => 'Wrong guard'],
            ['name' => 'identity.unregistered', 'guard_name' => 'web', 'alias_name' => 'Unknown'],
        ]);
        $expected = DB::table('permissions')->whereRaw('BINARY guard_name = ?', ['web'])
            ->whereIn('name', PermissionCatalogue::keys())->orderBy('name')
            ->get(['name', 'alias_name'])->map(fn ($row) => ['value' => $row->name, 'label' => $row->alias_name])->all();
        $this->assertCount(15, $expected);
        foreach (['index', 'show', 'store', 'update', 'delete'] as $capability) {
            $role = Role::create(['name' => 'fixture-permission-select-'.$capability, 'guard_name' => 'web']);
            $role->givePermissionTo(Permission::findByName('identity.roles.'.$capability, 'web'));
            $actor = $this->user('Fixture.Permission.'.$capability, $role->name);
            $browser = $this->browser($server);
            $this->assertSame(200, $browser->login($actor->username)->status);
            $response = $this->select($browser, 'permissions');
            $allowed = in_array($capability, ['store', 'update'], true);
            $this->assertSame($allowed ? 200 : 403, $response->status);
            $this->assertSame($allowed ? '1' : '0', $response->header('X-Test-Select-Reads'));
            if ($allowed) {
                $this->assertSame([['label' => 'permissions', 'options' => $expected]], $response->json()['data']);
                $this->assertSame('0', $response->header('X-Test-Select-Has-Limit'));
            } else {
                $this->assertArrayNotHasKey('data', $response->json());
            }
            foreach (['permissions,roles', 'roles,permissions', 'permissions,userFilterRoles,roles'] as $keys) {
                $mixed = $this->select($browser, $keys);
                $this->assertSame(403, $mixed->status);
                $this->assertSame('0', $mixed->header('X-Test-Select-Reads'));
                $this->assertArrayNotHasKey('data', $mixed->json());
            }
        }

        $manager = $this->user('Fixture.All.Selects');
        $browser = $this->browser($server);
        $this->assertSame(200, $browser->login($manager->username)->status);
        foreach (['permissions,roles,userFilterRoles', 'userFilterRoles,permissions,roles', 'roles,permissions'] as $keys) {
            $response = $this->select($browser, $keys);
            $this->assertSame(200, $response->status);
            $data = array_map(fn ($key) => [
                'label' => $key, 'options' => $key === 'permissions' ? $expected : $this->expected($key === 'roles'),
            ], explode(',', $keys));
            $this->assertSame(['success' => true, 'message' => '', 'data' => $data], $response->json());
            $this->assertSame((string) count($data), $response->header('X-Test-Select-Reads'));
        }
    }

    public function test_malformed_unknown_and_duplicate_keys_never_dispatch(): void
    {
        $actor = $this->user('Fixture.Manager');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        foreach (['', '?selects=', '?selects[]=roles', '?selects=roles,roles', '?selects=unknown',
            '?selects=roles,unknown', '?selects=roles,userFilterRoles,permissions,roles', '?selects=roles,userFilterRoles,roles', '?selects=Roles',
            '?selects=roles,', '?selects=,roles', '?selects=roles,%20userFilterRoles', '?selects=%20roles',
            '?selects=roles&sort=id', '?selects=roles&filter[isActive]=1',
            '?selects=roles&search=test', '?selects=roles&page=1', '?selects=roles&perPage=20',
            '?selects=roles&params[roles][search]=test', '?selects=roles,userFilterRoles,permissions,activities,activeActivities,roles',
        ] as $query) {
            $response = $browser->request('GET', '/api/v1/selects'.$query);
            $this->assertSame(422, $response->status, $query);
            $this->assertSame(['success' => false, 'message' => __('api.errors.validation'), 'errors' => ['selects' => [__('api.errors.validation')]]], $response->json());
            $this->assertSame('0', $response->header('X-Test-Select-Reads'));
            $this->assertTrue(Str::isUuid($response->header('X-Request-ID')));
        }
        $response = $browser->request('GET', '/api/v1/selects?selects=roles', options: ['raw' => '{"selects":"userFilterRoles"}']);
        $this->assertSame(422, $response->status);
        $this->assertSame('0', $response->header('X-Test-Select-Reads'));
    }

    public function test_complete_role_options_preserve_independent_active_visibility(): void
    {
        $actor = $this->user('Fixture.Manager');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        $rows = [];
        for ($i = 1; $i <= 198; $i++) {
            $rows[] = ['name' => 'fixture-complete-'.$i, 'guard_name' => 'web', 'is_active' => true];
        }
        DB::table('roles')->insert($rows);
        foreach ([200, 201, 202] as $count) {
            if ($count === 201) {
                Role::create(['name' => 'fixture-inactive-extra', 'guard_name' => 'web', 'is_active' => false]);
            } elseif ($count === 202) {
                Role::create(['name' => 'fixture-active-extra', 'guard_name' => 'web', 'is_active' => true]);
            }
            $response = $this->select($browser, 'roles,userFilterRoles');
            $this->assertSame(200, $response->status);
            $this->assertSame(['success' => true, 'message' => '', 'data' => [
                ['label' => 'roles', 'options' => $this->expected(true)],
                ['label' => 'userFilterRoles', 'options' => $this->expected(false)],
            ]], $response->json());
            $this->assertCount($count, $response->json()['data'][1]['options']);
            $this->assertCount($count === 200 ? 200 : $count - 1, $response->json()['data'][0]['options']);
            $this->assertSame('2', $response->header('X-Test-Select-Reads'));
            $this->assertSame('0', $response->header('X-Test-Select-Has-Limit'));
        }
        Role::query()->where('name', 'fixture-inactive-extra')->update(['is_active' => true]);
        $response = $this->select($browser, 'roles');
        $this->assertSame(200, $response->status);
        $this->assertSame([['label' => 'roles', 'options' => $this->expected(true)]], $response->json()['data']);
        $this->assertCount(202, $response->json()['data'][0]['options']);
        $this->assertSame('1', $response->header('X-Test-Select-Reads'));
        $this->assertSame('0', $response->header('X-Test-Select-Has-Limit'));
    }

    private function expected(bool $active): array
    {
        return DB::table('roles')->whereRaw('BINARY guard_name = ?', ['web'])
            ->when($active, fn ($query) => $query->where('is_active', true))
            ->orderBy('id')->get(['id', 'name'])->map(fn ($role) => ['value' => (int) $role->id, 'label' => $role->name])->all();
    }

    private function select(AuthenticationBrowser $browser, string $keys): AuthenticationHttpResponse
    {
        return $browser->request('GET', '/api/v1/selects?selects='.rawurlencode($keys));
    }
}
