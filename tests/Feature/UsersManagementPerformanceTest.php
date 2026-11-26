<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Queries\UsersQuery;
use Tests\Support\AuthenticationBrowser;
use Tests\Support\AuthenticationHttpResponse;
use Tests\Support\AvatarFixtures;
use Tests\Support\UsersRaceFixtures;

class UsersManagementPerformanceTest extends AuthenticationHttpTestCase
{
    use UsersRaceFixtures;

    public function test_measure_real_users_queries_and_explain_at_three_synthetic_scales(): void
    {
        $actor = $this->user('Perf.Manager');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($actor->username)->status);
        $manager = (int) Role::findByName('manager', 'web')->id;
        $reception = (int) Role::findByName('reception', 'web')->id;
        $fixtures = [(int) $actor->id => ['name' => $actor->name, 'username' => $actor->username, 'phone' => null, 'roleId' => $manager, 'isActive' => true]];
        $this->assertSame(1, (int) $actor->id);

        foreach ([100, 1000, 10000] as $size) {
            $rows = [];
            $pivots = [];
            for ($id = count($fixtures) + 1; $id <= $size; $id++) {
                $name = $id % 2 === 0 ? 'موظف تجريبي' : 'Synthetic Staff';
                $username = sprintf('Perf.User%05d', $id);
                $phone = sprintf('010%08d', $id);
                $role = $id % 2 === 0 ? $reception : $manager;
                $active = $id % 3 !== 0;
                $rows[] = [
                    'id' => $id, 'name' => $name, 'username' => $username, 'phone' => $phone,
                    'password' => $actor->password, 'is_active' => $active,
                    // Deliberately absent private files: the index must not read or validate each avatar.
                    'avatar_key' => md5('synthetic-avatar-'.$id).'.png',
                ];
                $pivots[] = ['role_id' => $role, 'model_type' => $actor->getMorphClass(), 'model_id' => $id];
                $fixtures[$id] = ['name' => $name, 'username' => $username, 'phone' => $phone, 'roleId' => $role, 'isActive' => $active];
                if (count($rows) === 500 || $id === $size) {
                    DB::table('users')->insert($rows);
                    DB::table('model_has_roles')->insert($pivots);
                    $rows = [];
                    $pivots = [];
                }
            }
            $this->assertSame($size, DB::table('users')->count());
            $this->assertSame($size, DB::table('model_has_roles')->count());
            $this->assertDirectoryDoesNotExist($this->barrierDir.'/avatars');

            $cases = [
                ['first15', [], 1, 15],
                ['first100', [], 1, 100],
                ['deep15', [], intdiv($size - 1, 15) + 1, 15],
                ['arabic', ['search' => 'موظف'], 1, 15],
                ['username', ['search' => 'Perf.User'], 1, 100],
                ['phone', ['search' => '010000'], 1, 15],
                ['role', ['roleId' => $reception], 1, 15],
                ['inactive', ['isActive' => '0'], 1, 100],
                ['combined', ['search' => 'Perf.User', 'roleId' => $reception, 'isActive' => '1'], 1, 15],
            ];
            foreach ($cases as [$label, $filter, $page, $perPage]) {
                $this->measureList($browser, $fixtures, $size, $label, $filter, $page, $perPage);
            }
            $this->assertDirectoryDoesNotExist($this->barrierDir.'/avatars');
        }
    }

    public function test_measure_actual_avatar_create_read_and_full_argon2id_with_lock_metrics(): void
    {
        $founder = $this->user('Perf.Founder', attributes: [
            'password' => Hash::make('test-fixture-only-password', ['memory' => 65536, 'time' => 4, 'threads' => 1]),
        ]);
        $this->assertSame(1, (int) $founder->id);
        foreach (['none', 'png', 'jpg', 'webp'] as $format) {
            // Use a fresh server process per case. RSS is its high-water mark,
            // including bootstrap/login; PHP peak is reset before the operation.
            $browser = $this->browser($this->server(fullCost: true));
            $this->assertSame(200, $browser->login($founder->username)->status);
            $username = 'Perf.Create.'.$format;
            $fields = $this->createFields($username);
            $image = $format === 'none' ? null : AvatarFixtures::image($format, 2048, 2048);
            if ($image !== null) {
                $this->assertLessThanOrEqual(2097152, strlen($image));
            }
            $options = ['headers' => ['X-Identity-Test-Mode' => 'users-performance']];
            if ($image !== null) {
                $boundary = 'kids-users-perf-fixture';
                $raw = '';
                foreach ($fields as $field => $value) {
                    $raw .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$field}\"\r\n\r\n{$value}\r\n";
                }
                $raw .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"avatar\"; filename=\"fixture.{$format}\"\r\nContent-Type: application/octet-stream\r\n\r\n{$image}\r\n--{$boundary}--\r\n";
                $options['raw'] = $raw;
                $options['headers']['Content-Type'] = 'multipart/form-data; boundary='.$boundary;
            }
            [$response, $ms] = $this->timed($browser, 'POST', '/api/v1/users', $image === null ? $fields : null, $options);
            $this->assertSame(201, $response->status, $response->header('X-Test-Exception-Class') ?? '');
            $user = User::query()->where('username', $username)->sole();
            $hash = password_get_info($user->password);
            $this->assertSame('argon2id', $hash['algoName']);
            $this->assertSame(['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1], $hash['options']);
            $this->assertSame('committed', $response->header('X-Test-Users-Outcome'));
            $this->assertNotNull($response->header('X-Test-Users-Lock-Wait-Ms'));
            $this->assertNotNull($response->header('X-Test-Users-Lock-Span-Ms'));
            $this->assertGreaterThan(0, (int) $response->header('X-Test-Php-Peak-Bytes'));
            $metrics = [
                'format' => $format, 'input_bytes' => $image === null ? 0 : strlen($image),
                'create_wall_ms' => $ms, 'create_queries' => (int) $response->header('X-Test-Query-Count'),
                'manager_acquire_ms' => (float) $response->header('X-Test-Users-Lock-Wait-Ms'),
                'manager_acquired_to_commit_ack_ms' => (float) $response->header('X-Test-Users-Lock-Span-Ms'),
                'manager_span_upper_bound_ms' => (float) $response->header('X-Test-Manager-Span-Ms'),
                'php_peak_bytes' => (int) $response->header('X-Test-Php-Peak-Bytes'),
                'process_peak_rss_linux_kib' => (int) $response->header('X-Test-Process-Peak-Rss'),
            ];
            if ($image !== null) {
                $this->assertNotNull($user->avatar_key);
                [$read, $readMs] = $this->timed($browser, 'GET', '/api/v1/users/'.$user->id.'/avatar', null, ['headers' => ['X-Identity-Test-Mode' => 'users-performance']]);
                $this->assertSame(200, $read->status);
                $this->assertSame([512, 512], array_slice(getimagesizefromstring($read->body), 0, 2));
                $this->assertSame($read->body, file_get_contents($this->barrierDir.'/avatars/'.$user->avatar_key));
                $metrics += [
                    'stored_bytes' => strlen($read->body), 'read_wall_ms' => $readMs,
                    'read_queries' => (int) $read->header('X-Test-Query-Count'),
                    'read_php_peak_bytes' => (int) $read->header('X-Test-Php-Peak-Bytes'),
                ];
            } else {
                $this->assertNull($user->avatar_key);
            }
            $this->emit('USERS_WRITE_PERF', $metrics);
            $browser->server->stop();
        }
    }

    private function measureList(AuthenticationBrowser $browser, array $fixtures, int $size, string $label, array $filter, int $page, int $perPage): void
    {
        $search = $filter['search'] ?? null;
        $role = $filter['roleId'] ?? null;
        $active = isset($filter['isActive']) ? $filter['isActive'] === '1' : null;
        $ids = array_keys(array_filter($fixtures, static function (array $fixture) use ($search, $role, $active): bool {
            return ($search === null || str_contains($fixture['name'], $search) || str_contains($fixture['username'], $search) || str_contains($fixture['phone'] ?? '', $search))
                && ($role === null || $fixture['roleId'] === $role)
                && ($active === null || $fixture['isActive'] === $active);
        }));
        rsort($ids);
        $expected = array_slice($ids, ($page - 1) * $perPage, $perPage);
        $this->assertNotEmpty($expected);
        $params = ['page' => $page, 'perPage' => $perPage];
        if ($filter !== []) {
            $params['filter'] = $filter;
        }
        $uri = '/api/v1/users?'.http_build_query($params);
        [$http, $httpMs] = $this->timed($browser, 'GET', $uri);
        $this->assertSame(200, $http->status);
        $data = $http->json()['data'];
        $this->assertSame($expected, array_column($data['items'], 'userId'));
        $this->assertSame(count($ids), $data['pagination']['total']);
        foreach (['Count', 'Page', 'Role'] as $kind) {
            $this->assertSame('1', $http->header('X-Test-Users-'.$kind.'-Reads'));
        }
        $this->assertSame('1', $http->header('X-Test-Users-Snapshot'));
        $this->assertSame((string) $perPage, $http->header('X-Test-Users-Page-Limit'));
        foreach ($data['items'] as $item) {
            $this->assertSame($fixtures[$item['userId']]['roleId'], $item['role']['id']);
            $this->assertSame($item['userId'] === 1 ? null : '/api/v1/users/'.$item['userId'].'/avatar', $item['avatarUrl']);
        }

        // Capture the real service's count/page/eager-role SQL, not a hand-written approximation.
        // This direct call is outside HTTP middleware only to collect synthetic-only SQL and bindings.
        $connection = DB::connection();
        $wasLogging = $connection->logging();
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        $started = hrtime(true);
        try {
            $direct = app(UsersQuery::class)->index([
                'requested' => $filter, 'search' => $search, 'roleId' => $role, 'isActive' => $active,
                'page' => $page, 'perPage' => $perPage,
            ], Request::create($uri, 'GET'));
            $directMs = round((hrtime(true) - $started) / 1000000, 3);
            $queries = $connection->getQueryLog();
        } finally {
            $connection->flushQueryLog();
            if (! $wasLogging) {
                $connection->disableQueryLog();
            }
        }
        $this->assertSame($data, $direct);
        $selects = array_values(array_filter($queries, static fn (array $entry): bool => str_starts_with(strtolower($entry['query']), 'select ')));
        $this->assertCount(3, $selects);
        $plans = [];
        foreach ($selects as $i => $entry) {
            $explain = DB::select('EXPLAIN '.$entry['query'], $entry['bindings']);
            $actual = DB::select('EXPLAIN ANALYZE '.$entry['query'], $entry['bindings']);
            $this->assertNotEmpty($explain);
            $this->assertNotEmpty($actual);
            $plans[] = [
                'kind' => ['count', 'page', 'roles'][$i],
                'sql_ms' => $entry['time'],
                'explain' => array_map(static fn ($row) => array_intersect_key((array) $row, array_flip(['table', 'type', 'possible_keys', 'key', 'rows', 'filtered', 'Extra'])), $explain),
                'analyze' => array_map(static fn ($row) => array_values((array) $row)[0], $actual),
            ];
        }
        $totalQueries = (int) $http->header('X-Test-Query-Count');
        $this->assertGreaterThanOrEqual(4, $totalQueries);
        $this->emit('USERS_READ_PERF', [
            'accounts' => $size, 'case' => $label, 'page' => $page, 'per_page' => $perPage,
            'filtered_total' => count($ids), 'returned' => count($expected),
            'http_ms' => $httpMs, 'direct_ms' => $directMs, 'domain_selects' => 3,
            'snapshot_setup' => 1, 'non_domain_http_queries' => $totalQueries - 4,
            'plans' => $plans,
        ]);
    }

    /** @return array{AuthenticationHttpResponse, float} */
    private function timed(AuthenticationBrowser $browser, string $method, string $uri, ?array $data = null, array $options = []): array
    {
        $start = hrtime(true);
        $response = $browser->request($method, $uri, $data, $options);

        return [$response, round((hrtime(true) - $start) / 1000000, 3)];
    }

    private function emit(string $prefix, array $metrics): void
    {
        // These are guarded synthetic measurements, never a hosting SLA.
        fwrite(STDOUT, $prefix.' '.json_encode($metrics, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL);
    }
}
