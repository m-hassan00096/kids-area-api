<?php

namespace Tests\Feature;

use App\Http\Middleware\ProtectSensitiveQueries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
}
