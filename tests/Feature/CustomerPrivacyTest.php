<?php

namespace Tests\Feature;

use App\Http\Middleware\ProtectSensitiveQueries;
use App\Services\Select\SelectRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\People\Models\Customer;
use RuntimeException;

class CustomerPrivacyTest extends IdentityDatabaseTestCase
{
    public function test_customer_path_suppresses_query_logging_and_events_then_restores_both(): void
    {
        $connection = DB::connection();
        $dispatcher = $connection->getEventDispatcher();
        $this->assertNotNull($dispatcher);
        $connection->enableQueryLog();
        $connection->flushQueryLog();
        $middleware = app(ProtectSensitiveQueries::class);

        try {
            foreach (['/api/v1/customers', '/api/v1/customers/1'] as $path) {
                $request = Request::create($path, 'POST');
                $response = $middleware->handle($request, function () use ($connection) {
                    $this->assertFalse($connection->logging());
                    $this->assertNull($connection->getEventDispatcher());
                    DB::table('customers')->where('name', 'Synthetic private name')->where('mobile', '00000000001')->exists();

                    return response()->json([]);
                });
                $this->assertSame(200, $response->getStatusCode());
                $this->assertTrue($connection->logging());
                $this->assertSame($dispatcher, $connection->getEventDispatcher());
                $this->assertSame([], $connection->getQueryLog());

                try {
                    $middleware->handle($request, function () use ($connection): never {
                        $this->assertFalse($connection->logging());
                        $this->assertNull($connection->getEventDispatcher());
                        DB::table('customers')->where('mobile', '00000000001')->exists();
                        throw new RuntimeException('Synthetic failure');
                    });
                    $this->fail('Expected the synthetic failure.');
                } catch (RuntimeException $failure) {
                    $this->assertSame('Synthetic failure', $failure->getMessage());
                }
                $this->assertTrue($connection->logging());
                $this->assertSame($dispatcher, $connection->getEventDispatcher());
                $this->assertSame([], $connection->getQueryLog());
            }
            $middleware->handle(Request::create('/api/v1/activities'), function () use ($connection, $dispatcher) {
                $this->assertTrue($connection->logging());
                $this->assertSame($dispatcher, $connection->getEventDispatcher());
                DB::table('activities')->count();

                return response()->json([]);
            });
            $this->assertCount(1, $connection->getQueryLog());
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
    }

    public function test_customer_provider_suppresses_events_and_logs_without_hiding_other_providers(): void
    {
        Customer::query()->create(['name' => 'Synthetic private name', 'mobile' => '00000000001']);
        $connection = DB::connection();
        $dispatcher = $connection->getEventDispatcher();
        $this->assertNotNull($dispatcher);
        $reads = 0;
        $fault = false;
        $connection->beforeExecuting(function (string $query) use ($connection, &$reads, &$fault): void {
            $sql = strtolower(str_replace(chr(96), '', $query));
            if (! str_starts_with($sql, 'select id, name, mobile from customers')) {
                return;
            }
            $reads++;
            $this->assertFalse($connection->logging());
            $this->assertNull($connection->getEventDispatcher());
            if ($fault) {
                throw new RuntimeException('Synthetic Customer provider failure');
            }
        });
        $registry = app(SelectRegistry::class);
        $permissions = ['people.customers.show', 'activities.index'];
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        try {
            $options = $registry->resolve('customers,activities', $permissions);
            $this->assertCount(1, $options[0]['options']);
            $this->assertSame(1, $reads);
            $this->assertTrue($connection->logging());
            $this->assertSame($dispatcher, $connection->getEventDispatcher());
            $this->assertCount(1, $connection->getQueryLog());
            $this->assertStringContainsString('activities', $connection->getQueryLog()[0]['query']);

            $connection->flushQueryLog();
            $fault = true;
            try {
                $registry->resolve('customers,activities', $permissions);
                $this->fail('Expected synthetic provider failure.');
            } catch (RuntimeException $failure) {
                $this->assertSame('Synthetic Customer provider failure', $failure->getMessage());
            }
            $this->assertSame(2, $reads);
            $this->assertSame([], $connection->getQueryLog());
            $this->assertTrue($connection->logging());
            $this->assertSame($dispatcher, $connection->getEventDispatcher());

            $fault = false;
            $connection->disableQueryLog();
            $registry->resolve('customers', $permissions);
            $this->assertSame(3, $reads);
            $this->assertFalse($connection->logging());
            $this->assertSame($dispatcher, $connection->getEventDispatcher());
            $this->assertSame([], $connection->getQueryLog());
        } finally {
            $fault = false;
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
    }
}
