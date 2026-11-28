<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\Role;
use Modules\People\Enums\CustomerStatus;
use Modules\People\Models\Customer;
use Modules\People\Services\Select\CustomerOptionsProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AuthenticationBrowser;
use Tests\Support\AuthenticationHttpResponse;
use Tests\Support\AuthenticationRaceHarness;
use Tests\Support\UsersRaceFixtures;

class CustomerPerformanceTest extends AuthenticationHttpTestCase
{
    use AuthenticationRaceHarness, UsersRaceFixtures;

    #[DataProvider('scales')]
    public function test_measure_complete_customer_options_pk_reads_and_writes(int $size): void
    {
        $actor = $this->user('Customer.Perf.Manager');
        $admin = $this->browser($this->server());
        $this->assertSame(200, $admin->login($actor->username)->status);
        $this->fixtures($size);
        $this->assertSame($size, Customer::count());
        $eligible = $size - intdiv($size, 5);
        $expected = DB::table('customers')->where('is_active', CustomerStatus::ACTIVE->value)
            ->orderBy('name')->orderBy('id')->get(['id', 'name', 'mobile'])
            ->map(static fn (object $row): array => [
                'value' => (int) $row->id,
                'label' => $row->mobile === null ? $row->name : $row->name.' - '.$row->mobile,
            ])->all();
        $this->assertCount($eligible, $expected);
        $this->assertSame($eligible, DB::table('customers')->where('is_active', 1)->count());

        // Count domain SQL shapes without enabling query logging or retaining bindings.
        $reads = 0;
        $observe = true;
        $db = DB::connection();
        $db->beforeExecuting(function (string $query) use (&$reads, &$observe, $db): void {
            if (! $observe) {
                return;
            }
            $sql = strtolower(str_replace(chr(96), '', $query));
            if (str_starts_with($sql, 'select') && preg_match('/\bfrom customers\b/', $sql) === 1) {
                $reads++;
                $this->assertSame('select id, name, mobile from customers where is_active = ? order by name asc, id asc', $sql);
                $this->assertFalse($db->logging());
                $this->assertNull($db->getEventDispatcher());
            }
        });
        $started = hrtime(true);
        try {
            $direct = (new CustomerOptionsProvider)->options();
            $directMs = round((hrtime(true) - $started) / 1000000, 3);
        } finally {
            $observe = false;
        }
        $this->assertSame(1, $reads);
        $this->assertSame($expected, $direct);
        unset($direct);

        [$response, $metrics] = $this->sampleRead($admin, '/api/v1/selects?selects=customers', ['label' => 'customers', 'options' => $expected], true);
        $this->assertSame($eligible, count($response->json()['data'][0]['options']));
        $selectPlan = $this->plan('EXPLAIN SELECT id, name, mobile FROM customers WHERE is_active = ? ORDER BY name ASC, id ASC', [CustomerStatus::ACTIVE->value]);
        $this->emit('CUSTOMER_SELECT_PERF', [
            'size' => $size, 'eligible' => $eligible, 'materialized' => $eligible,
            'domain_selects' => $reads, 'direct_ms' => $directMs, 'plan' => $selectPlan,
        ] + $metrics);

        // Include a retained inactive record: authorized detail is not active-only.
        $row = Customer::query()->findOrFail($size);
        $this->assertSame(CustomerStatus::INACTIVE, $row->is_active);
        $detail = ['id' => (int) $row->id, 'name' => $row->name, 'mobile' => $row->mobile];
        [, $detailMetrics] = $this->sampleRead($admin, '/api/v1/customers/'.$size, $detail, false);
        $pkPlan = $this->plan('EXPLAIN SELECT id, name, mobile FROM customers WHERE customers.id = ? LIMIT 1', [$size]);
        $this->assertSame('PRIMARY', $pkPlan[0]['key']);
        $this->emit('CUSTOMER_DETAIL_PERF', ['size' => $size, 'domain_selects' => 1, 'plan' => $pkPlan] + $detailMetrics);
        $this->assertSame($size, Customer::count());

        // Real native POST and PUT samples, after the exact-size read benchmark.
        // Each POST increases the fixture by one; report actual rows before writes.
        $samples = ['create' => [], 'update' => []];
        for ($n = 1; $n <= 3; $n++) {
            $fields = ['name' => 'Synthetic measured create '.$n, 'mobile' => '99'.str_pad((string) $n, 9, '0', STR_PAD_LEFT)];
            $start = hrtime(true);
            $created = $admin->request('POST', '/api/v1/customers', $fields, $this->raceOptions('customer-performance', 'measure'));
            $samples['create'][] = ['rows_before' => $size + $n - 1] + $this->writeMetrics($created, 201, (hrtime(true) - $start) / 1000000);
            $id = $created->json()['data']['id'];
            $this->assertSame(CustomerStatus::ACTIVE, Customer::query()->findOrFail($id)->is_active);
            $this->assertSame(['id' => $id] + $fields, $created->json()['data']);

            $start = hrtime(true);
            $updated = $admin->request('PUT', '/api/v1/customers/'.$size, ['name' => 'Synthetic measured update '.$n], $this->raceOptions('customer-performance', 'measure'));
            $samples['update'][] = ['rows_before' => $size + $n] + $this->writeMetrics($updated, 200, (hrtime(true) - $start) / 1000000);
            $this->assertSame('Synthetic measured update '.$n, $row->fresh()->name);
            $this->assertNull($row->fresh()->mobile);
            $this->assertSame(CustomerStatus::INACTIVE, $row->fresh()->is_active);
        }
        $this->assertSame($size + 3, Customer::count());
        foreach ($samples as $kind => $values) {
            $this->emit('CUSTOMER_WRITE_PERF', [
                'size' => $size, 'kind' => $kind, 'samples' => $values,
                'wall_ms' => $this->summary(array_column($values, 'wall_ms')),
                'manager_wait_ms' => $this->summary(array_column($values, 'manager_wait_ms')),
                'manager_span_ms' => $this->summary(array_column($values, 'manager_span_ms')),
                'commit_ms' => $this->summary(array_column($values, 'commit_ms')),
            ]);
        }
    }

    public static function scales(): array
    {
        return [[200], [500], [2000], [10000]];
    }

    public function test_measure_two_customer_writes_and_permission_revocation_contention(): void
    {
        $manager = $this->user('Customer.Perf.Contention.Manager');
        $role = Role::create(['name' => 'Synthetic measured writer', 'guard_name' => 'web']);
        $role->givePermissionTo('people.customers.update');
        $staff = $this->user('Customer.Perf.Contention.Staff', $role->name);
        $admin = $this->browser($this->server());
        $writer = $this->browser($this->server());
        $this->assertSame(200, $admin->login($manager->username)->status);
        $this->assertSame(200, $writer->login($staff->username)->status);
        $this->fixtures(200);
        foreach (['customer-write', 'permission-revocation'] as $kind) {
            $first = $writer->prepare('PUT', '/api/v1/customers/1', ['name' => 'Synthetic held '.$kind], $this->raceOptions('customer-performance-hold-update', 'first'));
            $second = $kind === 'customer-write'
                ? $admin->prepare('PUT', '/api/v1/customers/2', ['name' => 'Synthetic waiting writer'], $this->raceOptions('customer-performance', 'second'))
                : $admin->prepare('PUT', '/api/v1/roles/'.$role->id, ['name' => $role->name, 'isActive' => true, 'permissions' => []], $this->raceOptions('customer-performance', 'second'));
            [$one, $two] = $this->race($first, $second, 'manager');
            $this->assertSame(200, $one->status);
            $this->assertSame(200, $two->status);
            $this->assertSame('committed', $one->header('X-Test-Customer-Outcome'));
            $this->assertSame('committed', $two->header($kind === 'customer-write' ? 'X-Test-Customer-Outcome' : 'X-Test-Roles-Outcome'));
            $this->assertSame('Synthetic held '.$kind, Customer::query()->findOrFail(1)->name);
            if ($kind === 'customer-write') {
                $this->assertSame('Synthetic waiting writer', Customer::query()->findOrFail(2)->name);
            } else {
                $this->assertSame([], $role->fresh()->permissions()->pluck('name')->all());
                $this->assertSame(403, $writer->request('PUT', '/api/v1/customers/1', ['name' => 'Synthetic denied'])->status);
            }
            $this->emit('CUSTOMER_CONTENTION_PERF', [
                'size' => 200, 'contender' => $kind, 'first' => $this->lockMetrics($one), 'second' => $this->lockMetrics($two),
                'scope' => 'deliberate test barrier; not organic traffic latency',
            ]);
        }
        $this->assertSame(200, Customer::count());
        $this->emit('CUSTOMER_PERF_ENV', [
            'mysql' => DB::selectOne('SELECT VERSION() AS version')->version,
            'php' => PHP_VERSION, 'scales' => [200, 500, 2000, 10000], 'samples_per_read_write_case' => 3,
            'scope' => 'guarded isolated CI; synthetic records; no production SLA',
        ]);
    }

    private function fixtures(int $size): void
    {
        $rows = [];
        for ($i = 1; $i <= $size; $i++) {
            $rows[] = [
                'id' => $i,
                'name' => ($i % 2 === 0 ? 'قياس عميل ' : 'Synthetic Customer ').($i % 17),
                'mobile' => $i % 3 === 0 ? null : str_pad((string) $i, 11, '0', STR_PAD_LEFT),
                'is_active' => $i % 5 === 0 ? CustomerStatus::INACTIVE->value : CustomerStatus::ACTIVE->value,
            ];
            if (count($rows) === 200 || $i === $size) {
                DB::table('customers')->insert($rows);
                $rows = [];
            }
        }
        $this->assertSame(intdiv($size, 3), DB::table('customers')->whereNull('mobile')->count());
        $this->assertSame(intdiv($size, 5), DB::table('customers')->where('is_active', CustomerStatus::INACTIVE->value)->count());
    }

    private function sampleRead(AuthenticationBrowser $browser, string $uri, array $expected, bool $select): array
    {
        $times = [];
        $memory = [];
        $counts = [];
        $bytes = [];
        for ($i = 0; $i < 3; $i++) {
            $start = hrtime(true);
            $response = $browser->request('GET', $uri, options: ['headers' => ['X-Identity-Test-Mode' => 'customer-performance']]);
            $times[] = round((hrtime(true) - $start) / 1000000, 3);
            $this->assertSame(200, $response->status);
            $this->assertSame(['success' => true, 'message' => '', 'data' => $select ? [$expected] : $expected], $response->json());
            $this->assertSame('1', $response->header('X-Test-Customer-Reads'));
            $this->assertSame('0', $response->header('X-Test-Customer-Writes'));
            if ($select) {
                $this->assertSame('1', $response->header('X-Test-Select-Reads'));
                $this->assertSame('0', $response->header('X-Test-Select-Has-Limit'));
            }
            $memory[] = (int) $response->header('X-Test-Php-Peak-Bytes');
            $counts[] = (int) $response->header('X-Test-Query-Count');
            $bytes[] = strlen($response->body);
            $this->assertGreaterThan(0, $memory[$i]);
            $this->assertGreaterThanOrEqual(1, $counts[$i]);
            $this->assertGreaterThan(0, $bytes[$i]);
        }

        return [$response, [
            'samples' => $times, 'http_ms' => $this->summary($times), 'php_peak_bytes' => max($memory),
            'total_http_queries' => $counts, 'response_bytes' => $bytes,
        ]];
    }

    private function plan(string $sql, array $bindings): array
    {
        // Only fixed synthetic EXPLAIN shapes and safe optimizer metadata.
        $rows = DB::select($sql, $bindings);
        $this->assertNotEmpty($rows);

        return array_map(static fn (object $row): array => array_intersect_key((array) $row, array_flip(['table', 'type', 'possible_keys', 'key', 'rows', 'filtered', 'Extra'])), $rows);
    }

    private function writeMetrics(AuthenticationHttpResponse $response, int $status, float $wall): array
    {
        $this->assertSame($status, $response->status);
        $this->assertSame('committed', $response->header('X-Test-Customer-Outcome'));
        $this->assertSame('1', $response->header('X-Test-Customer-Writes'));

        return ['wall_ms' => round($wall, 3)] + $this->lockMetrics($response);
    }

    private function lockMetrics(AuthenticationHttpResponse $response): array
    {
        $metrics = [];
        foreach (['manager_wait_ms' => 'Lock-Wait-Ms', 'manager_span_ms' => 'Lock-Span-Ms', 'commit_ms' => 'Commit-Ms'] as $field => $header) {
            $value = $response->header('X-Test-Customer-'.$header);
            $this->assertNotNull($value);
            $this->assertGreaterThanOrEqual(0, (float) $value);
            $metrics[$field] = (float) $value;
        }
        $metrics['php_peak_bytes'] = (int) $response->header('X-Test-Php-Peak-Bytes');
        $metrics['total_http_queries'] = (int) $response->header('X-Test-Query-Count');
        $metrics['response_bytes'] = strlen($response->body);
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
