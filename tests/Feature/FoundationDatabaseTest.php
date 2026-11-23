<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FoundationDatabaseTest extends TestCase
{
    public function test_isolated_mysql_eight_uses_innodb_and_utf8mb4(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('mysql', DB::getDefaultConnection());
        $this->assertSame('kids_area_test', DB::connection()->getDatabaseName());

        $row = DB::selectOne(
            'SELECT VERSION() AS version, @@default_storage_engine AS engine, @@character_set_connection AS charset'
        );

        $this->assertStringStartsWith('8.', $row->version);
        $this->assertSame('InnoDB', $row->engine);
        $this->assertSame('utf8mb4', $row->charset);
    }
}
