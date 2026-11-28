<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\People\Models\Customer;
use Tests\Support\AuthenticationHttpResponse;
use Tests\Support\AuthenticationRaceHarness;
use Tests\Support\UsersRaceFixtures;

class CustomerFaultHttpTest extends AuthenticationHttpTestCase
{
    use AuthenticationRaceHarness, UsersRaceFixtures;

    public function test_failures_before_and_after_dml_and_unrelated_native_check_rollback_safely(): void
    {
        $actor = $this->user('Customer.Fault.Manager');
        $admin = $this->browser($this->server());
        $this->assertSame(200, $admin->login($actor->username)->status);
        $target = Customer::query()->create(['name' => 'Synthetic original', 'mobile' => '00000000001']);
        $path = '/api/v1/customers/'.$target->id;
        $before = $this->snapshot();
        foreach ([
            ['POST', '/api/v1/customers', 'customer-fault-before-insert', '1'],
            ['POST', '/api/v1/customers', 'customer-fault-after-insert', '1'],
            ['PUT', $path, 'customer-fault-after-update', '1'],
            ['POST', '/api/v1/customers', 'customer-fault-other-constraint', '2'],
        ] as [$method, $uri, $mode, $writes]) {
            $response = $admin->request($method, $uri, ['name' => 'Synthetic private failure', 'mobile' => '00000000002'], $this->inspectCustomer($mode));
            $this->assertFailure($response, 500, 'rolled_back');
            $this->assertSame($writes, $response->header('X-Test-Customer-Writes'));
            $this->assertSame('2', $response->header('X-Test-Customer-Timeout-Statements'));
            if ($mode === 'customer-fault-other-constraint') {
                $this->assertSame('3819', $response->header('X-Test-Customer-MySQL-Code'));
                $this->assertSame('1', $response->header('X-Test-Customer-MySQL-Count'));
            }
            $this->assertSame($before, $this->snapshot());
        }
        $this->assertSame(200, $admin->me()->status);
    }

    public function test_actual_lock_timeout_and_failed_restore_keep_safe_outcome(): void
    {
        $actor = $this->user('Customer.Timeout.Manager');
        $admin = $this->browser($this->server());
        $this->assertSame(200, $admin->login($actor->username)->status);
        $target = Customer::query()->create(['name' => 'Synthetic timeout target']);
        $before = $this->snapshot();
        DB::beginTransaction();
        try {
            DB::table('roles')->where('name', 'manager')->where('guard_name', 'web')->lockForUpdate()->first(['id']);
            $started = microtime(true);
            $response = $admin->request('PUT', '/api/v1/customers/'.$target->id, ['name' => 'Synthetic timeout request'], $this->inspectCustomer('customer-timeout-restore'));
            $elapsed = microtime(true) - $started;
            $this->assertGreaterThanOrEqual(4.5, $elapsed);
            $this->assertLessThan(20, $elapsed);
            $this->assertFailure($response, 503, 'rolled_back');
            $this->assertSame('1205', $response->header('X-Test-Customer-MySQL-Code'));
            $this->assertSame('1', $response->header('X-Test-Customer-MySQL-Count'));
            $this->assertSame('0', $response->header('X-Test-Customer-Writes'));
            $this->assertSame('1', $response->header('X-Test-Customer-Restore-Warning'));
        } finally {
            DB::rollBack();
        }
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(200, $admin->me()->status);
    }

    public function test_actual_innodb_deadlock_is_not_retried(): void
    {
        $actor = $this->user('Customer.Deadlock.Manager');
        $admin = $this->browser($this->server());
        $external = $this->browser($this->server());
        $this->assertSame(200, $admin->login($actor->username)->status);
        $target = Customer::query()->create(['name' => 'Synthetic deadlock target']);
        $before = $this->snapshot();
        [$first, $second] = $this->race(
            $external->prepare('POST', '/api/v1/__fixture-roles-deadlock/'.$actor->id, options: $this->raceOptions('hold-roles-deadlock', 'first')),
            $admin->prepare('PUT', '/api/v1/customers/'.$target->id, ['name' => 'Synthetic must not commit'], $this->inspectCustomer('customer-observe-mysql', 'second')),
            'users',
        );
        $this->assertSame(201, $first->status);
        $this->assertFailure($second, 503, 'rolled_back');
        $this->assertSame('1213', $second->header('X-Test-Customer-MySQL-Code'));
        $this->assertSame('1', $second->header('X-Test-Customer-MySQL-Count'));
        $this->assertSame('0', $second->header('X-Test-Customer-Writes'));
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(100, DB::table('identity_login_throttles')->where('scope', 250)->count());
    }

    public function test_lost_commit_acknowledgement_and_session_save_failure_never_replay(): void
    {
        $actor = $this->user('Customer.Commit.Manager');
        $admin = $this->browser($this->server());
        $this->assertSame(200, $admin->login($actor->username)->status);

        // Optional mobile makes blind replay especially unsafe: no unique key
        // could deduplicate these two legitimate identical registrations.
        $response = $admin->request('POST', '/api/v1/customers', ['name' => 'Synthetic committed uncertain'], $this->inspectCustomer('customer-commit-ack'));
        $this->assertFailure($response, 503, 'unknown');
        $this->assertSame('1', $response->header('X-Test-Actual-Commit-Count'));
        $this->assertSame('1', $response->header('X-Test-Customer-Writes'));
        $row = Customer::query()->where('name', 'Synthetic committed uncertain')->sole();
        $this->assertSame(1, Customer::count());
        $this->assertNull($row->mobile);

        $response = $admin->request('PUT', '/api/v1/customers/'.$row->id, ['name' => 'Synthetic committed before session fault'], $this->inspectCustomer('customer-native-save-fault'));
        $this->assertFailure($response, 500, 'committed');
        $this->assertSame('1', $response->header('X-Test-Customer-Writes'));
        $this->assertSame('Synthetic committed before session fault', $row->fresh()->name);
        $this->assertSame(1, Customer::count());
        $this->assertSame(200, $admin->me()->status);
    }

    private function assertFailure(AuthenticationHttpResponse $response, int $status, string $outcome): void
    {
        $this->assertSame($status, $response->status, $response->header('X-Test-Exception-Class') ?? '');
        $this->assertSame(['success' => false, 'message' => __($status === 503 ? 'api.errors.unavailable' : 'api.errors.internal'), 'errors' => []], $response->json());
        $this->assertSame($outcome, $response->header('X-Test-Customer-Outcome'));
        $this->assertSame('1', $response->header('X-Test-Customer-Outcome-Reported'));
        $this->assertSame('1', $response->header('X-Test-Redacted'));
        $this->assertSame('0', $response->header('X-Test-Customer-Query-Visible'));
    }

    private function inspectCustomer(string $mode, ?string $barrier = null): array
    {
        $headers = ['X-Identity-Test-Mode' => $mode, 'X-Identity-Test-Inspect' => '1'];
        if ($barrier !== null) {
            $headers['X-Identity-Test-Barrier'] = $barrier;
        }

        return ['headers' => $headers];
    }

    private function snapshot(): array
    {
        return DB::table('customers')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all();
    }
}
