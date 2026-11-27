<?php

namespace Tests\Feature;

use App\Services\Select\SelectRegistry;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Support\PermissionCatalogue;

class SelectRegistryGrowthTest extends AuthenticationHttpTestCase
{
    public function test_five_providers_return_all_eligible_options_at_measured_scales(): void
    {
        $manager = $this->user('Fixture.Select.Growth');
        $browser = $this->browser($this->server());
        $this->assertSame(200, $browser->login($manager->username)->status);
        $this->assertSame(2, DB::table('roles')->count());
        $this->assertSame(0, DB::table('activities')->count());
        $keys = ['activeActivities', 'roles', 'permissions', 'userFilterRoles', 'activities'];
        $uri = '/api/v1/selects?selects='.implode(',', $keys);
        $permissions = PermissionCatalogue::keys();
        $previous = 0;

        foreach ([200, 201, 500, 501, 1000, 2000, 10000] as $size) {
            $this->insertFixtures($previous, $size);
            $previous = $size;
            $expected = $this->expectedOptions($keys);
            $this->assertCount($size, $expected[3]['options']);
            $this->assertCount($size, $expected[4]['options']);
            $this->assertCount(count($permissions), $expected[2]['options']);
            $this->assertLessThan($size, count($expected[0]['options']));
            $this->assertLessThan($size, count($expected[1]['options']));

            $httpTimes = [];
            $responseBytes = [];
            $peakBytes = [];
            $queryCounts = [];
            for ($sample = 0; $sample < 3; $sample++) {
                $start = hrtime(true);
                $response = $browser->request('GET', $uri, options: ['headers' => ['X-Identity-Test-Mode' => 'activity-performance']]);
                $httpTimes[] = round((hrtime(true) - $start) / 1000000, 3);
                $this->assertSame(200, $response->status);
                $this->assertSame('5', $response->header('X-Test-Select-Reads'));
                $this->assertSame('0', $response->header('X-Test-Select-Has-Limit'));
                $this->assertSame('2', $response->header('X-Test-Activity-Reads'));
                $this->assertSame('0', $response->header('X-Test-Activity-Writes'));
                $this->assertSame(['success' => true, 'message' => '', 'data' => $expected], $response->json());
                $responseBytes[] = strlen($response->body);
                $peakBytes[] = (int) $response->header('X-Test-Php-Peak-Bytes');
                $queryCounts[] = (int) $response->header('X-Test-Query-Count');
                $this->assertGreaterThan(0, $peakBytes[$sample]);
                $this->assertGreaterThanOrEqual(5, $queryCounts[$sample]);
            }

            $directTimes = [];
            $sqlTimes = array_fill_keys($keys, []);
            $plans = [];
            for ($sample = 0; $sample < 3; $sample++) {
                [$direct, $queries, $elapsed] = $this->measureProviders($keys, $permissions);
                $this->assertSame($expected, $direct);
                $this->assertCount(5, $queries);
                $directTimes[] = $elapsed;
                foreach ($queries as $index => $entry) {
                    $key = $keys[$index];
                    $this->assertMatchesRegularExpression('/\Aselect\b/i', $entry['query']);
                    $this->assertDoesNotMatchRegularExpression('/\blimit\b|\bcount\s*\(/i', $entry['query']);
                    $sqlTimes[$key][] = $entry['time'];
                    if ($sample === 0) {
                        $explain = DB::select('EXPLAIN '.$entry['query'], $entry['bindings']);
                        $analyze = DB::select('EXPLAIN ANALYZE '.$entry['query'], $entry['bindings']);
                        $this->assertNotEmpty($explain);
                        $this->assertNotEmpty($analyze);
                        $plans[$key] = [
                            'sql' => $entry['query'],
                            'explain' => array_map(static fn ($row) => array_intersect_key((array) $row, array_flip(['table', 'type', 'possible_keys', 'key', 'rows', 'filtered', 'Extra'])), $explain),
                            'analyze' => array_map(static fn ($row) => array_values((array) $row)[0], $analyze),
                        ];
                    }
                }
            }

            fwrite(STDOUT, 'SELECT_REGISTRY_GROWTH '.json_encode([
                'fixture_rows_per_scalable_catalogue' => $size,
                'options_by_key' => array_combine($keys, array_map(static fn ($item) => count($item['options']), $expected)),
                'provider_selects' => 5,
                'http_ms' => $this->summary($httpTimes),
                'direct_ms' => $this->summary($directTimes),
                'sql_ms_by_key' => array_map(fn ($values) => $this->summary($values), $sqlTimes),
                'response_bytes' => max($responseBytes),
                'php_peak_bytes' => max($peakBytes),
                'total_http_queries' => $queryCounts,
                'samples' => count($httpTimes),
                'plans' => $plans,
                'scope' => 'guarded synthetic MySQL 8 CI; no production SLA',
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL);
            $this->assertSame($size, DB::table('roles')->count());
            $this->assertSame($size, DB::table('activities')->count());
        }
    }

    private function insertFixtures(int $previous, int $size): void
    {
        $roles = [];
        $activities = [];
        for ($i = $previous + 1; $i <= $size; $i++) {
            $name = ($i % 2 === 0 ? 'قياس ' : 'Synthetic ').str_pad((string) $i, 5, '0', STR_PAD_LEFT);
            if ($i > 2) {
                $roles[] = ['name' => 'Role '.$name, 'guard_name' => 'web', 'is_active' => $i % 5 !== 0];
            }
            $activities[] = ['name' => 'Activity '.$name, 'supports_hourly' => true,
                'supports_subscription' => false, 'hourly_rate' => '1.00', 'is_active' => $i % 5 !== 0];
            if (count($activities) === 200 || $i === $size) {
                if ($roles !== []) {
                    DB::table('roles')->insert($roles);
                }
                DB::table('activities')->insert($activities);
                $roles = [];
                $activities = [];
            }
        }
    }

    private function expectedOptions(array $keys): array
    {
        $project = static fn ($row) => ['value' => (int) $row->id, 'label' => $row->name];
        $roleOptions = static fn (bool $active): array => DB::table('roles')->whereRaw('BINARY guard_name = ?', ['web'])
            ->when($active, fn ($query) => $query->where('is_active', true))
            ->orderBy('id')->get(['id', 'name'])->map($project)->all();
        $activityOptions = static fn (bool $active): array => DB::table('activities')->when($active, fn ($query) => $query->where('is_active', true))
            ->orderBy('name')->orderBy('id')->get(['id', 'name'])->map($project)->all();
        $permissionOptions = DB::table('permissions')->whereRaw('BINARY guard_name = ?', ['web'])
            ->whereIn('name', PermissionCatalogue::keys())->orderBy('name')->get(['name', 'alias_name'])
            ->map(static fn ($row) => ['value' => $row->name, 'label' => $row->alias_name])->all();
        $options = [
            'roles' => $roleOptions(true), 'userFilterRoles' => $roleOptions(false),
            'activities' => $activityOptions(false), 'activeActivities' => $activityOptions(true),
            'permissions' => $permissionOptions,
        ];

        return array_map(static fn ($key) => ['label' => $key, 'options' => $options[$key]], $keys);
    }

    private function measureProviders(array $keys, array $permissions): array
    {
        $db = DB::connection();
        $wasLogging = $db->logging();
        $db->flushQueryLog();
        $db->enableQueryLog();
        $start = hrtime(true);
        try {
            $result = app(SelectRegistry::class)->resolve(implode(',', $keys), $permissions);
            $ms = round((hrtime(true) - $start) / 1000000, 3);
            $queries = $db->getQueryLog();
        } finally {
            $db->flushQueryLog();
            if (! $wasLogging) {
                $db->disableQueryLog();
            }
        }

        return [$result, $queries, $ms];
    }

    private function summary(array $values): array
    {
        sort($values, SORT_NUMERIC);

        return ['median' => $values[intdiv(count($values), 2)], 'max' => max($values)];
    }
}
