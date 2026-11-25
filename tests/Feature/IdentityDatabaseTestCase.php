<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

abstract class IdentityDatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $connection = DB::connection();

        // Never reset any database that is not the dedicated, local test database.
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('mysql', $connection->getDriverName());
        $this->assertSame('kids_area_test', $connection->getDatabaseName());
        $this->assertSame('kids_area_ci', $connection->getConfig('username'));
        $this->assertContains($connection->getConfig('host'), ['127.0.0.1', 'localhost']);
        $server = $connection->selectOne('SELECT DATABASE() AS db, VERSION() AS version, @@default_storage_engine AS engine');
        $this->assertSame('kids_area_test', $server->db);
        $this->assertStringStartsWith('8.', $server->version);
        $this->assertSame('InnoDB', $server->engine);

        config()->set('hashing.argon.memory', 8192);
        config()->set('hashing.argon.time', 2);

        $this->assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]), Artisan::output());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
