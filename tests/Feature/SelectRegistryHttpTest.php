<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Tests\Support\AuthenticationBrowser;
use Tests\Support\AuthenticationHttpResponse;

class SelectRegistryHttpTest extends AuthenticationHttpTestCase
{
    public function test_independent_capabilities_and_all_or_nothing_dispatch(): void
    {
        $server = $this->server();
        $inactive = Role::create(['name' => 'fixture-inactive', 'guard_name' => 'web', 'alias_name' => 'دور غير نشط', 'is_active' => false]);
        Role::create(['name' => 'fixture-other', 'guard_name' => 'other', 'alias_name' => 'Other']);
        foreach (['create', 'update', 'view', 'delete'] as $capability) {
            $name = 'fixture-select-'.$capability;
            $role = Role::create(['name' => $name, 'guard_name' => 'web', 'alias_name' => 'Synthetic '.$capability]);
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

    public function test_malformed_unknown_and_duplicate_keys_never_dispatch(): void
    {
        $actor = $this->user('Fixture.Manager');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        foreach (['', '?selects=', '?selects[]=roles', '?selects=roles,roles', '?selects=permissions',
            '?selects=roles,permissions', '?selects=roles,userFilterRoles,roles', '?selects=Roles',
            '?selects=roles,', '?selects=,roles', '?selects=roles,%20userFilterRoles', '?selects=%20roles',
            '?selects=roles&sort=id', '?selects=roles&filter[isActive]=1',
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

    public function test_exact_cap_and_overflow_are_bounded_without_partial_results(): void
    {
        $actor = $this->user('Fixture.Manager');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        $rows = [];
        for ($i = 1; $i <= 198; $i++) {
            $rows[] = ['name' => 'fixture-cap-'.$i, 'guard_name' => 'web', 'alias_name' => 'Role '.$i, 'is_active' => true];
        }
        DB::table('roles')->insert($rows);
        $response = $this->select($browser, 'roles,userFilterRoles');
        $this->assertSame(200, $response->status);
        $this->assertCount(200, $response->json()['data'][0]['options']);
        $this->assertCount(200, $response->json()['data'][1]['options']);
        $this->assertSame('2', $response->header('X-Test-Select-Reads'));
        $this->assertSame('201', $response->header('X-Test-Select-Limit'));
        $overflow = Role::create(['name' => 'fixture-cap-overflow', 'guard_name' => 'web', 'alias_name' => 'Overflow', 'is_active' => false]);
        $this->assertSame(200, $this->select($browser, 'roles')->status);
        $response = $this->select($browser, 'roles,userFilterRoles');
        $this->assertSame(409, $response->status);
        $this->assertSame(['success' => false, 'message' => __('identity.select_options_limit'), 'errors' => ['code' => 'SELECT_OPTIONS_LIMIT']], $response->json());
        $this->assertSame('2', $response->header('X-Test-Select-Reads'));
        $this->assertSame('201', $response->header('X-Test-Select-Limit'));
        $overflow->update(['is_active' => true]);
        $response = $this->select($browser, 'roles');
        $this->assertSame(409, $response->status);
        $this->assertArrayNotHasKey('data', $response->json());
        $this->assertSame('1', $response->header('X-Test-Select-Reads'));
        $this->assertSame('201', $response->header('X-Test-Select-Limit'));
    }

    private function expected(bool $active): array
    {
        return DB::table('roles')->whereRaw('BINARY guard_name = ?', ['web'])
            ->when($active, fn ($query) => $query->where('is_active', true))
            ->orderBy('id')->get(['id', 'alias_name'])->map(fn ($role) => ['value' => (int) $role->id, 'label' => $role->alias_name])->all();
    }

    private function select(AuthenticationBrowser $browser, string $keys): AuthenticationHttpResponse
    {
        return $browser->request('GET', '/api/v1/selects?selects='.rawurlencode($keys));
    }
}
