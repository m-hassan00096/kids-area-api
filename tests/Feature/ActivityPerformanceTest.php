<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Activities\Enums\ActivityBillingMode;
use Modules\Activities\Models\Activity;
use Modules\Activities\Queries\ActivitiesQuery;
use Modules\Activities\Services\Select\ActivityOptionsProvider;
use Modules\Identity\Models\Role;
use Tests\Support\ActivityHttpFixtures;
use Tests\Support\AuthenticationRaceHarness;
use Tests\Support\UsersRaceFixtures;

class ActivityPerformanceTest extends AuthenticationHttpTestCase
{
    use ActivityHttpFixtures, AuthenticationRaceHarness, UsersRaceFixtures;

    private const PROJECTION = ['id', 'name', 'supports_hourly', 'supports_subscription', 'hourly_rate', 'monthly_price', 'is_active'];

    public function test_measure_real_catalogue_reads_and_writes_at_approved_scales(): void
    {
        $actor = $this->user('Activity.Perf.Manager');
        $admin = $this->browser($this->server());
        $this->assertSame(200, $admin->login($actor->username)->status);
        $this->emit('ACTIVITY_PERF_ENV', [
            'mysql' => DB::selectOne('SELECT VERSION() AS version')->version,
            'php' => PHP_VERSION, 'staff' => 1, 'roles' => 2,
            'samples_per_case' => 3, 'scope' => 'guarded CI, no host SLA',
        ]);
        $previous = 0;
        foreach ([200, 2000] as $size) {
            $this->fixtures($previous + 1, $size);
            $previous = $size;
            $this->assertSame($size, Activity::count());
            $before = $this->activitySnapshot();
            foreach ([
                ['first15', [], '-id', 1, 15],
                ['first100', [], '-id', 1, 100],
                ['deep15', [], 'id', intdiv($size - 1, 15) + 1, 15],
                ['name-asc', [], 'name', 1, 100],
                ['name-desc', [], '-name', 1, 15],
                ['active', ['isActive' => '1'], '-id', 1, 15],
                ['inactive', ['isActive' => '0'], 'name', 1, 15],
                ['hourly', ['billingMode' => '0'], 'id', 1, 100],
                ['subscription', ['billingMode' => '1'], '-id', 1, 100],
                ['arabic-literal', ['search' => 'قياس %_!'], 'name', 1, 15],
                ['english-case', ['search' => 'perf'], '-name', 1, 15],
                ['accent', ['search' => 'Café'], 'id', 1, 15],
                ['combined', ['search' => 'قياس', 'isActive' => '1', 'billingMode' => '0'], 'name', 1, 15],
                ['empty', ['search' => 'No synthetic match'], '-id', 1, 15],
                ['out-of-range', [], '-id', PHP_INT_MAX, 100],
            ] as [$label, $filter, $sort, $page, $perPage]) {
                $this->measureList($size, $admin, $label, $filter, $sort, $page, $perPage);
            }
            $uri = '/api/v1/activities/'.$size;
            [$http, $metrics] = $this->sampleHttp($admin, $uri, 200, 2);
            [$detail, $plans, $directMs] = $this->measureSql(
                fn () => app(ActivitiesQuery::class)->detail($size, Request::create($uri)), 1, ['detail'],
            );
            $this->assertSame($this->activityData(Activity::query()->findOrFail($size)), $detail);
            $this->assertSame($detail, $http->json()['data']);
            $this->assertSame('1', $http->header('X-Test-Activity-Target-Reads'));
            $this->emit('ACTIVITY_DETAIL_PERF', ['size' => $size, 'domain_selects' => 1, 'admission_selects' => 1,
                'direct_ms' => $directMs, 'plans' => $plans] + $metrics);
            foreach (['activities' => false, 'activeActivities' => true] as $key => $activeOnly) {
                $this->measureSelect($size, $admin, $key, $activeOnly);
            }
            $this->assertSame($before, $this->activitySnapshot());

            // Full HTTP writes with real mutex acquisition and COMMIT timing.
            // Rename the same row repeatedly; retain the catalogue size.
            $target = Activity::query()->findOrFail($size);
            $writes = [];
            for ($n = 1; $n <= 3; $n++) {
                $body = $this->activityFields('Perf write '.$size.'-'.$n);
                $start = hrtime(true);
                $response = $admin->request('PUT', $uri, $body, $this->raceOptions('activity-performance', 'measure'));
                $writes[] = $this->writeMetrics($response, (hrtime(true) - $start) / 1000000);
                $this->assertSame($body['name'], $target->fresh()->name);
            }
            $this->emit('ACTIVITY_WRITE_PERF', ['size' => $size, 'samples' => $writes,
                'wall_ms' => $this->summary(array_column($writes, 'wall_ms')),
                'manager_wait_ms' => $this->summary(array_column($writes, 'manager_wait_ms')),
                'manager_span_ms' => $this->summary(array_column($writes, 'manager_span_ms')),
                'commit_ms' => $this->summary(array_column($writes, 'commit_ms'))]);
            $this->assertSame($size, Activity::count());
        }
    }

    public function test_measure_manager_lock_contention_with_two_writes_and_role_edit(): void
    {
        $actor = $this->user('Activity.Perf.Race');
        $a = $this->browser($this->server());
        $b = $this->browser($this->server());
        $this->assertSame(200, $a->login($actor->username)->status);
        $this->assertSame(200, $b->login($actor->username)->status);
        $this->fixtures(1, 200);
        $role = Role::create(['name' => 'Perf editable role', 'guard_name' => 'web']);
        $role->givePermissionTo('activities.index');
        foreach (['activity', 'role'] as $kind) {
            $first = $a->prepare('PUT', '/api/v1/activities/1', $this->activityFields('Held '.$kind), $this->raceOptions('activity-performance-hold-update', 'first'));
            $second = $kind === 'activity'
                ? $b->prepare('PUT', '/api/v1/activities/2', $this->activityFields('Waiting activity'), $this->raceOptions('activity-performance', 'second'))
                : $b->prepare('PUT', '/api/v1/roles/'.$role->id, [
                    'name' => 'Perf edited role', 'isActive' => true, 'permissions' => ['activities.show'],
                ], $this->raceOptions('activity-performance', 'second'));
            [$one, $two] = $this->race($first, $second, 'manager');
            $this->assertSame(200, $one->status);
            $this->assertSame(200, $two->status);
            $this->assertSame('committed', $one->header('X-Test-Activity-Outcome'));
            $this->assertSame('committed', $two->header($kind === 'role' ? 'X-Test-Roles-Outcome' : 'X-Test-Activity-Outcome'));
            $this->assertSame('Held '.$kind, Activity::query()->findOrFail(1)->name);
            if ($kind === 'activity') {
                $this->assertSame('Waiting activity', Activity::query()->findOrFail(2)->name);
            } else {
                $this->assertSame(['activities.show'], $role->fresh()->permissions()->pluck('name')->all());
            }
            $this->emit('ACTIVITY_CONTENTION_PERF', ['size' => 200, 'contender' => $kind,
                'first' => $this->lockMetrics($one), 'second' => $this->lockMetrics($two),
                'scope' => 'deliberate barrier hold, not organic latency']);
        }
        $this->assertSame(200, Activity::count());
    }

    private function fixtures(int $start, int $end): void
    {
        $rows = [];
        for ($i = $start; $i <= $end; $i++) {
            $hourly = $i % 3 !== 1;
            $subscription = $i % 3 !== 0;
            $prefix = match ($i % 4) {
                0 => 'قياس %_!\\, ',
                1 => 'Perf English ',
                2 => 'Café ',
                3 => 'Cafe ',
            };
            $rows[] = ['id' => $i, 'name' => $prefix.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'supports_hourly' => $hourly, 'supports_subscription' => $subscription,
                'hourly_rate' => $hourly ? '60.50' : null, 'monthly_price' => $subscription ? '800.00' : null,
                'is_active' => $i % 5 !== 0];
            if (count($rows) === 200 || $i === $end) {
                DB::table('activities')->insert($rows);
                $rows = [];
            }
        }
    }

    private function measureList(int $size, $admin, string $label, array $filter, string $sort, int $page, int $perPage): void
    {
        $search = $filter['search'] ?? null;
        $active = isset($filter['isActive']) ? $filter['isActive'] === '1' : null;
        $mode = isset($filter['billingMode']) ? ActivityBillingMode::from((int) $filter['billingMode']) : null;
        $expectedQuery = Activity::query();
        // Independently select expected IDs with explicit fixture conditions.
        if ($search !== null) {
            $expectedQuery->whereRaw("name LIKE ? ESCAPE '!'", ['%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%']);
        }
        if ($active !== null) {
            $expectedQuery->where('is_active', $active);
        }
        if ($mode !== null) {
            $expectedQuery->where($mode === ActivityBillingMode::HOURLY ? 'supports_hourly' : 'supports_subscription', true);
        }
        $total = (clone $expectedQuery)->count();
        $last = max(1, intdiv($total, $perPage) + (int) ($total % $perPage !== 0));
        $column = ltrim($sort, '-');
        $expectedQuery->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc');
        if ($column === 'name') {
            $expectedQuery->orderBy('id');
        }
        $items = $total !== 0 && $page <= $last
            ? $expectedQuery->forPage($page, $perPage)->get(self::PROJECTION)->map(fn ($row) => $this->activityData($row))->all()
            : [];
        $expected = ['items' => $items, 'pagination' => ['total' => $total, 'currentPage' => $page, 'perPage' => $perPage, 'lastPage' => $last]];
        $uri = '/api/v1/activities?'.http_build_query(['filter' => $filter, 'sort' => $sort, 'page' => $page, 'perPage' => $perPage]);
        $count = $items === [] ? 1 : 2;
        [$http, $metrics] = $this->sampleHttp($admin, $uri, 200, $count);
        $this->assertSame($expected, $http->json()['data']);
        $this->assertSame($items === [] ? '' : (string) $perPage, $http->header('X-Test-Activity-Page-Limit') ?? '');
        $criteria = ['requested' => $filter, 'search' => $search, 'isActive' => $active, 'billingMode' => $mode,
            'sort' => $sort, 'page' => $page, 'perPage' => $perPage];
        [$direct, $plans, $directMs] = $this->measureSql(
            fn () => app(ActivitiesQuery::class)->index($criteria, Request::create($uri)), $count, ['count', 'page'],
        );
        $this->assertSame($expected, $direct);
        $this->emit('ACTIVITY_READ_PERF', ['size' => $size, 'case' => $label, 'returned' => count($items), 'filtered_total' => $total,
            'page' => $page, 'per_page' => $perPage, 'domain_selects' => $count, 'direct_ms' => $directMs, 'plans' => $plans] + $metrics);
    }

    private function measureSelect(int $size, $admin, string $key, bool $activeOnly): void
    {
        $expected = DB::table('activities')->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('name')->orderBy('id')->get(['id', 'name'])
            ->map(static fn ($row) => ['value' => (int) $row->id, 'label' => $row->name])->all();
        [$http, $metrics] = $this->sampleHttp($admin, '/api/v1/selects?selects='.$key, 200, 1);
        $this->assertSame('1', $http->header('X-Test-Select-Reads'));
        $this->assertSame('0', $http->header('X-Test-Select-Has-Limit'));
        [$direct, $plans, $ms] = $this->measureSql(fn () => (new ActivityOptionsProvider($activeOnly))->options(), 1, ['provider']);
        $this->assertDoesNotMatchRegularExpression('/\blimit\b/i', $plans[0]['sql']);
        $this->assertSame($expected, $direct);
        $this->assertSame([['label' => $key, 'options' => $expected]], $http->json()['data']);
        $this->emit('ACTIVITY_SELECT_PERF', ['size' => $size, 'key' => $key, 'matching' => count($expected),
            'materialized' => count($direct), 'response_bytes' => strlen($http->body), 'direct_ms' => $ms, 'plans' => $plans] + $metrics);
    }

    private function sampleHttp($admin, string $uri, int $status, int $domainSelects): array
    {
        $times = [];
        $memory = [];
        $queries = [];
        $response = null;
        for ($i = 0; $i < 3; $i++) {
            $start = hrtime(true);
            $response = $admin->request('GET', $uri, options: ['headers' => ['X-Identity-Test-Mode' => 'activity-performance']]);
            $times[] = round((hrtime(true) - $start) / 1000000, 3);
            $this->assertSame($status, $response->status);
            $this->assertSame((string) $domainSelects, $response->header('X-Test-Activity-Reads'));
            $this->assertSame('0', $response->header('X-Test-Activity-Writes'));
            $memory[] = (int) $response->header('X-Test-Php-Peak-Bytes');
            $queries[] = (int) $response->header('X-Test-Query-Count');
            $this->assertGreaterThan(0, $memory[$i]);
            $this->assertGreaterThanOrEqual($domainSelects, $queries[$i]);
        }

        return [$response, ['http_ms' => $this->summary($times), 'php_peak_bytes' => max($memory),
            'total_http_queries' => $queries, 'samples' => count($times)]];
    }

    private function measureSql(callable $read, int $expected, array $kinds): array
    {
        $db = DB::connection();
        $wasLogging = $db->logging();
        $db->flushQueryLog();
        $db->enableQueryLog();
        $start = hrtime(true);
        try {
            $result = $read();
            $ms = round((hrtime(true) - $start) / 1000000, 3);
            $queries = $db->getQueryLog();
        } finally {
            $db->flushQueryLog();
            if (! $wasLogging) {
                $db->disableQueryLog();
            }
        }
        $selects = array_values(array_filter($queries, static fn ($entry) => str_starts_with(strtolower($entry['query']), 'select ')));
        $this->assertCount($expected, $selects);
        $plans = [];
        foreach ($selects as $i => $entry) {
            $this->assertStringContainsString('activities', strtolower($entry['query']));
            $explain = DB::select('EXPLAIN '.$entry['query'], $entry['bindings']);
            $analyze = DB::select('EXPLAIN ANALYZE '.$entry['query'], $entry['bindings']);
            $this->assertNotEmpty($explain);
            $this->assertNotEmpty($analyze);
            $plans[] = ['kind' => $kinds[$i], 'sql' => $entry['query'], 'sql_ms' => $entry['time'],
                'explain' => array_map(static fn ($row) => array_intersect_key((array) $row, array_flip(['table', 'type', 'possible_keys', 'key', 'rows', 'filtered', 'Extra'])), $explain),
                'analyze' => array_map(static fn ($row) => array_values((array) $row)[0], $analyze)];
        }

        return [$result, $plans, $ms];
    }

    private function writeMetrics($response, float $wall): array
    {
        $this->assertSame(200, $response->status);
        $this->assertSame('committed', $response->header('X-Test-Activity-Outcome'));
        $this->assertSame('1', $response->header('X-Test-Activity-Writes'));

        return ['wall_ms' => round($wall, 3)] + $this->lockMetrics($response);
    }

    private function lockMetrics($response): array
    {
        $metrics = [];
        foreach (['manager_wait_ms' => 'Lock-Wait-Ms', 'manager_span_ms' => 'Lock-Span-Ms', 'commit_ms' => 'Commit-Ms'] as $field => $header) {
            $value = $response->header('X-Test-Activity-'.$header);
            $this->assertNotNull($value);
            $this->assertGreaterThanOrEqual(0, (float) $value);
            $metrics[$field] = (float) $value;
        }
        $metrics['php_peak_bytes'] = (int) $response->header('X-Test-Php-Peak-Bytes');
        $metrics['total_http_queries'] = (int) $response->header('X-Test-Query-Count');
        $this->assertGreaterThan(0, $metrics['php_peak_bytes']);
        $this->assertGreaterThan(0, $metrics['total_http_queries']);

        return $metrics;
    }

    private function summary(array $values): array
    {
        sort($values, SORT_NUMERIC);

        return ['median' => $values[intdiv(count($values), 2)], 'max' => max($values)];
    }

    private function emit(string $marker, array $metrics): void
    {
        fwrite(STDOUT, $marker.' '.json_encode($metrics, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL);
    }
}
