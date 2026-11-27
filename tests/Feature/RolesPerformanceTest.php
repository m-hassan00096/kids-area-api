<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\Role;
use Modules\Identity\Queries\RolesQuery;
use Modules\Identity\Support\PermissionCatalogue;

class RolesPerformanceTest extends AuthenticationHttpTestCase
{
    public function test_actual_roles_queries_at_three_paired_synthetic_scales(): void
    {
        $manager = $this->user('Perf.RoleManager');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($manager->username)->status);
        $roleFixtures = [];
        foreach (Role::query()->orderBy('id')->get() as $role) {
            $roleFixtures[(int) $role->id] = [
                'roleId' => (int) $role->id, 'name' => $role->name,
                'usersCount' => (int) ($role->name === 'manager'),
                'permissionsCount' => $role->permissions()->count(),
                'isActive' => true, 'isProtected' => true,
            ];
        }
        $permissionIds = DB::table('permissions')->orderBy('id')->pluck('id', 'name');
        $previousUsers = 1;
        foreach ([[10, 100], [100, 1000], [200, 10000]] as [$roles, $users]) {
            $grants = [];
            for ($id = count($roleFixtures) + 1; $id <= $roles; $id++) {
                $name = ($id % 2 === 0 ? 'قياس %_! ' : 'Perf Role ').$id;
                $active = $id % 3 !== 0;
                DB::table('roles')->insert(['id' => $id, 'name' => $name, 'guard_name' => 'web', 'is_active' => $active]);
                $keys = match ($id % 3) {
                    0 => [],
                    1 => ['identity.roles.show'],
                    2 => ['identity.roles.show', 'identity.users.view'],
                };
                foreach ($keys as $key) {
                    $grants[] = ['role_id' => $id, 'permission_id' => $permissionIds[$key]];
                }
                $roleFixtures[$id] = ['roleId' => $id, 'name' => $name, 'usersCount' => 0,
                    'permissionsCount' => count($keys), 'isActive' => $active, 'isProtected' => false];
            }
            if ($grants !== []) {
                DB::table('role_has_permissions')->insert($grants);
            }
            $accounts = [];
            $memberships = [];
            for ($id = $previousUsers + 1; $id <= $users; $id++) {
                // Strongly skewed membership: 80% of new accounts on a role
                // in the first page, others across active and inactive roles.
                $roleId = $id % 5 !== 0 ? $roles : 1 + $id % $roles;
                $accounts[] = ['id' => $id, 'name' => 'Synthetic role staff', 'username' => sprintf('Perf.Roles%05d', $id),
                    'password' => $manager->password, 'is_active' => $id % 3 !== 0];
                $memberships[] = ['role_id' => $roleId, 'model_type' => $manager->getMorphClass(), 'model_id' => $id];
                $roleFixtures[$roleId]['usersCount']++;
                if (count($accounts) === 500 || $id === $users) {
                    DB::table('users')->insert($accounts);
                    DB::table('model_has_roles')->insert($memberships);
                    $accounts = [];
                    $memberships = [];
                }
            }
            $previousUsers = $users;
            $this->assertSame($roles, Role::count());
            $this->assertSame($users, DB::table('users')->count());
            $this->assertSame($users, DB::table('model_has_roles')->count());
            $this->assertSame($users, array_sum(array_column($roleFixtures, 'usersCount')));

            foreach ([
                ['first15', [], 1, 15],
                ['first100', [], 1, 100],
                ['deep15', [], intdiv($roles - 1, 15) + 1, 15],
                ['active', ['isActive' => '1'], 1, 100],
                ['inactive', ['isActive' => '0'], 1, 15],
                ['literal', ['search' => '%_!'], 1, 100],
                ['arabic', ['search' => 'قياس'], 1, 15],
                ['combined', ['search' => 'قياس', 'isActive' => '1'], 1, 15],
                ['empty', ['search' => 'no-synthetic-role-match'], 1, 15],
                ['out-of-range', [], $roles + 1, 100],
            ] as [$label, $filter, $page, $perPage]) {
                $search = $filter['search'] ?? null;
                $active = isset($filter['isActive']) ? $filter['isActive'] === '1' : null;
                $matched = array_values(array_filter($roleFixtures, static fn (array $role): bool => ($search === null || str_contains($role['name'], $search))
                    && ($active === null || $role['isActive'] === $active)));
                usort($matched, static fn (array $a, array $b): int => $b['roleId'] <=> $a['roleId']);
                $expected = ['items' => array_slice($matched, ($page - 1) * $perPage, $perPage),
                    'pagination' => ['total' => count($matched), 'currentPage' => $page, 'perPage' => $perPage,
                        'lastPage' => max(1, (int) ceil(count($matched) / $perPage))]];
                $uri = '/api/v1/roles?'.http_build_query(['filter' => $filter, 'page' => $page, 'perPage' => $perPage]);
                $start = hrtime(true);
                $http = $browser->request('GET', $uri);
                $wall = (hrtime(true) - $start) / 1000000;
                $this->assertSame(200, $http->status, $label);
                $this->assertSame($expected, $http->json()['data']);
                $count = $expected['items'] === [] ? 1 : 4;
                $this->assertSame((string) $count, $http->header('X-Test-Roles-Reads'));
                $this->assertSame('1', $http->header('X-Test-Users-Snapshot'));
                $criteria = ['requested' => $filter, 'search' => $search, 'isActive' => $active, 'page' => $page, 'perPage' => $perPage];
                [$direct, $plans, $ms] = $this->measure(fn () => app(RolesQuery::class)->index($criteria, Request::create($uri, 'GET')), $count,
                    ['count', 'page', 'users-count', 'grants-count']);
                $this->assertSame($expected, $direct);
                $this->emit('ROLES_READ_PERF', ['roles' => $roles, 'users' => $users, 'case' => $label, 'returned' => count($expected['items']),
                    'filtered_total' => count($matched), 'page' => $page, 'per_page' => $perPage, 'domain_selects' => $count,
                    'snapshot_setup' => 1, 'http_ms' => round($wall, 3), 'direct_ms' => $ms,
                    'total_http_queries' => (int) $http->header('X-Test-Query-Count'), 'plans' => $plans]);
            }
            $uri = '/api/v1/roles/'.$roles;
            $start = hrtime(true);
            $detail = $browser->request('GET', $uri);
            $wall = (hrtime(true) - $start) / 1000000;
            $this->assertSame(200, $detail->status);
            $this->assertSame('4', $detail->header('X-Test-Roles-Reads'));
            $this->assertSame('1', $detail->header('X-Test-Users-Snapshot'));
            $data = $detail->json()['data'];
            $granted = $data['permissions'];
            unset($data['permissions']);
            $this->assertSame($roleFixtures[$roles], $data);
            $this->assertCount($data['permissionsCount'], $granted);
            [$direct, $plans, $ms] = $this->measure(fn () => app(RolesQuery::class)->detail($roles, Request::create($uri, 'GET')), 3,
                ['detail', 'users-count', 'grants']);
            $this->assertSame($detail->json()['data'], $direct);
            $this->emit('ROLES_DETAIL_PERF', ['roles' => $roles, 'users' => $users, 'assigned_to_target' => $data['usersCount'],
                'domain_selects' => 3, 'admission_selects' => 1, 'http_ms' => round($wall, 3), 'direct_ms' => $ms, 'plans' => $plans]);

            $start = hrtime(true);
            $selects = $browser->request('GET', '/api/v1/selects?selects=roles,userFilterRoles,permissions');
            $this->assertSame(200, $selects->status);
            $this->assertSame('3', $selects->header('X-Test-Select-Reads'));
            $this->assertSame('201', $selects->header('X-Test-Select-Limit'));
            $options = $selects->json()['data'];
            $this->assertSame(array_values(array_map(static fn ($role) => ['value' => $role['roleId'], 'label' => $role['name']], array_filter($roleFixtures, static fn ($role) => $role['isActive']))), $options[0]['options']);
            $this->assertSame(array_values(array_map(static fn ($role) => ['value' => $role['roleId'], 'label' => $role['name']], $roleFixtures)), $options[1]['options']);
            $this->assertCount(count(PermissionCatalogue::keys()), $options[2]['options']);
            $this->emit('ROLES_SELECT_PERF', ['roles' => $roles, 'users' => $users, 'options' => array_map(static fn ($item) => count($item['options']), $options),
                'provider_selects' => 3, 'cap_plus_one' => 201, 'http_ms' => round((hrtime(true) - $start) / 1000000, 3)]);
        }
    }

    public function test_measure_disable_role_at_one_hundred_and_thousand_members(): void
    {
        $manager = $this->user('Perf.DisableManager');
        $unaffected = $this->user('Perf.DisableOther', 'reception');
        $observer = $this->browser($this->server());
        $this->assertSame(200, $observer->login($unaffected->username)->status);
        foreach ([1, 100, 1000] as $size) {
            $role = Role::create(['name' => 'Perf Disable '.$size, 'guard_name' => 'web']);
            $role->givePermissionTo('identity.roles.show');
            $first = $this->user('Perf.Native'.$size, $role->name);
            // One real native browser and a deterministic bulk set of synthetic
            // session binding rows. The bulk payloads are deliberately NOT valid
            // native cookies; they measure indexed revocation, not browser login.
            $device = $this->browser($this->server());
            $this->assertSame(200, $device->login($first->username)->status);
            $ids = [(int) $first->id];
            $nextId = (int) DB::table('users')->max('id') + 1;
            $accounts = [];
            $memberships = [];
            $sessions = [];
            for ($i = 1; $i < $size; $i++) {
                $id = $nextId + $i - 1;
                $version = $i % 4 + 1;
                $accounts[] = ['id' => $id, 'name' => 'Synthetic revoke staff', 'username' => 'Perf.Disable.'.$size.'.'.$i,
                    'password' => $manager->password, 'is_active' => $i % 3 !== 0, 'auth_version' => $version];
                $memberships[] = ['role_id' => $role->id, 'model_type' => $manager->getMorphClass(), 'model_id' => $id];
                $ids[] = $id;
                for ($n = 0; $n < 2; $n++) {
                    $sessions[] = ['id' => sha1('synthetic-role-revoke-'.$id.'-'.$n), 'user_id' => $id,
                        'payload' => 'synthetic-not-native-payload', 'last_activity' => time(),
                        'auth_user_id' => $id, 'auth_version' => $version, 'auth_last_activity' => time()];
                }
                if (count($accounts) === 250 || $i === $size - 1) {
                    DB::table('users')->insert($accounts);
                    DB::table('model_has_roles')->insert($memberships);
                    DB::table('sessions')->insert($sessions);
                    $accounts = [];
                    $memberships = [];
                    $sessions = [];
                }
            }
            $admin = $this->browser($this->server());
            $this->assertSame(200, $admin->login($manager->username)->status);
            $before = DB::table('users')->whereIn('id', $ids)->orderBy('id')->get(['id', 'auth_version', 'is_active', 'password'])->all();
            $bound = DB::table('sessions')->whereIn('auth_user_id', $ids)->count();
            $this->assertSame($size, count($before));
            $this->assertSame(2 * ($size - 1) + 1, $bound);
            $path = '/api/v1/roles/'.$role->id;
            $start = hrtime(true);
            $response = $admin->request('PUT', $path, ['name' => $role->name, 'isActive' => false, 'permissions' => ['identity.roles.show']],
                ['headers' => ['X-Identity-Test-Mode' => 'roles-performance']]);
            $wall = (hrtime(true) - $start) / 1000000;
            $this->assertSame(200, $response->status, $response->header('X-Test-Exception-Class') ?? '');
            $this->assertSame('committed', $response->header('X-Test-Roles-Outcome'));
            $this->assertSame((string) $size, $response->header('X-Test-Roles-Generation-Writes'));
            $this->assertSame((string) $size, $response->header('X-Test-Roles-Session-Deletes'));
            $this->assertFalse($role->fresh()->is_active);
            $this->assertSame($size, DB::table('model_has_roles')->where('role_id', $role->id)->count());
            $this->assertSame(0, DB::table('sessions')->whereIn('auth_user_id', $ids)->count());
            $after = DB::table('users')->whereIn('id', $ids)->orderBy('id')->get(['id', 'auth_version', 'is_active', 'password'])->all();
            foreach ($before as $index => $old) {
                $this->assertSame($old->id, $after[$index]->id);
                $this->assertSame((int) $old->auth_version + 1, (int) $after[$index]->auth_version);
                $this->assertSame($old->is_active, $after[$index]->is_active);
                $this->assertSame($old->password, $after[$index]->password);
            }
            foreach (['Lock-Wait-Ms', 'Lock-Span-Ms', 'Commit-Ms'] as $metric) {
                $this->assertNotNull($response->header('X-Test-Roles-'.$metric));
                $this->assertGreaterThanOrEqual(0, (float) $response->header('X-Test-Roles-'.$metric));
            }
            $this->assertGreaterThan(0, (int) $response->header('X-Test-Php-Peak-Bytes'));
            $this->assertGreaterThan(0, (int) $response->header('X-Test-Process-Peak-Rss'));
            $queries = (int) $response->header('X-Test-Query-Count');
            $this->assertGreaterThanOrEqual(2 * $size, $queries);
            $this->assertLessThanOrEqual(2 * $size + 100, $queries, 'No per-member extra read or transaction.');
            $this->emit('ROLES_DISABLE_PERF', ['members' => $size, 'all_users' => DB::table('users')->count(),
                'removed_binding_rows' => $bound, 'native_sessions' => 1, 'synthetic_binding_rows' => $bound - 1,
                'wall_ms' => round($wall, 3), 'queries' => $queries, 'generation_writes' => $size, 'session_deletes' => $size,
                'manager_wait_ms' => (float) $response->header('X-Test-Roles-Lock-Wait-Ms'),
                'manager_acquired_to_commit_ack_ms' => (float) $response->header('X-Test-Roles-Lock-Span-Ms'),
                'commit_ms' => (float) $response->header('X-Test-Roles-Commit-Ms'),
                'php_peak_bytes' => (int) $response->header('X-Test-Php-Peak-Bytes'),
                'process_peak_rss_linux_kib' => (int) $response->header('X-Test-Process-Peak-Rss')]);
            $this->assertSame(200, $admin->request('PUT', $path, ['name' => $role->name, 'isActive' => true, 'permissions' => ['identity.roles.show']])->status);
            $this->assertEquals($after, DB::table('users')->whereIn('id', $ids)->orderBy('id')->get(['id', 'auth_version', 'is_active', 'password'])->all());
            $this->assertSame(401, $device->me()->status);
            $this->assertSame(200, $observer->me()->status);
            $this->assertSame($unaffected->auth_version, $unaffected->fresh()->auth_version);
            $this->assertSame(200, $admin->me()->status);
            $this->assertSame(0, DB::table('sessions')->whereIn('auth_user_id', $ids)->count());
            $admin->server->stop();
            $device->server->stop();
        }
    }

    private function measure(callable $read, int $expected, array $kinds): array
    {
        $connection = DB::connection();
        $wasLogging = $connection->logging();
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        $start = hrtime(true);
        try {
            $data = $read();
            $ms = round((hrtime(true) - $start) / 1000000, 3);
            $queries = $connection->getQueryLog();
        } finally {
            $connection->flushQueryLog();
            if (! $wasLogging) {
                $connection->disableQueryLog();
            }
        }
        $selects = array_values(array_filter($queries, static fn ($entry) => str_starts_with(strtolower($entry['query']), 'select ')));
        $this->assertCount($expected, $selects);
        $plans = [];
        foreach ($selects as $i => $entry) {
            $explain = DB::select('EXPLAIN '.$entry['query'], $entry['bindings']);
            $analyze = DB::select('EXPLAIN ANALYZE '.$entry['query'], $entry['bindings']);
            $this->assertNotEmpty($explain);
            $this->assertNotEmpty($analyze);
            $plans[] = ['kind' => $kinds[$i], 'sql_ms' => $entry['time'],
                'explain' => array_map(static fn ($row) => array_intersect_key((array) $row, array_flip(['table', 'type', 'possible_keys', 'key', 'rows', 'filtered', 'Extra'])), $explain),
                'analyze' => array_map(static fn ($row) => array_values((array) $row)[0], $analyze)];
        }

        return [$data, $plans, $ms];
    }

    private function emit(string $marker, array $metrics): void
    {
        fwrite(STDOUT, $marker.' '.json_encode($metrics, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL);
    }
}
