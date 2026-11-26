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
        $this->assertSame('true', getenv('GITHUB_ACTIONS'));
        $this->assertSame('', getenv('DB_URL'));
        $this->assertSame('mysql', $connection->getDriverName());
        $this->assertSame('kids_area_test', $connection->getDatabaseName());
        $this->assertSame('kids_area_ci', $connection->getConfig('username'));
        $this->assertSame('127.0.0.1', $connection->getConfig('host'));
        $server = $connection->selectOne('SELECT DATABASE() AS db, CURRENT_USER() AS principal, VERSION() AS version, @@default_storage_engine AS engine');
        $this->assertSame('kids_area_test', $server->db);
        $this->assertStringStartsWith('kids_area_ci@', $server->principal);
        $this->assertStringStartsWith('8.', $server->version);
        $this->assertSame('InnoDB', $server->engine);

        config()->set('hashing.argon.memory', 8192);
        config()->set('hashing.argon.time', 2);

        // Reuse the approved fixture initialization. Historical forward tests can
        // start from their exact baseline without rolling back a newer migration.
        $arguments = ['--force' => true];
        $paths = $this->fixtureMigrationPaths();
        if ($paths !== []) {
            $arguments['--path'] = $paths;
        }
        $this->assertSame(0, Artisan::call('migrate:fresh', $arguments), Artisan::output());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function fixtureMigrationPaths(): array
    {
        return [];
    }
}
