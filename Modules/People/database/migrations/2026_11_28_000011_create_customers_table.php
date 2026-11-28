<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'mysql') {
            throw new RuntimeException('Customers requires reviewed MySQL 8 CHECK enforcement, strict SQL mode and UTC.');
        }

        $server = $connection->selectOne('SELECT VERSION() AS version, @@SESSION.sql_mode AS sql_mode, TIMEDIFF(NOW(), UTC_TIMESTAMP()) AS utc_offset');
        if (preg_match('/\A8\.[0-9]+\.[0-9]+/', $server->version, $version) !== 1
            || version_compare($version[0], '8.0.16', '<')
            || (! str_contains($server->sql_mode, 'STRICT_TRANS_TABLES') && ! str_contains($server->sql_mode, 'STRICT_ALL_TABLES'))
            || $server->utc_offset !== '00:00:00') {
            throw new RuntimeException('Customers requires reviewed MySQL 8 CHECK enforcement, strict SQL mode and UTC.');
        }

        // Exact approved DDL, verified by the isolated Customer preflight.
        // One atomic CREATE; no separate ALTER, permission writes or data seed.
        $connection->statement(<<<'SQL'
            CREATE TABLE customers (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                mobile VARCHAR(11) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
                is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY uq_customers_mobile (mobile),
                CONSTRAINT chk_customers_status CHECK (is_active IN (0,1)),
                CONSTRAINT chk_customers_name CHECK (CHAR_LENGTH(TRIM(name)) > 0),
                CONSTRAINT chk_customers_mobile CHECK (
                    mobile IS NULL OR
                    (CHAR_LENGTH(mobile) = 11 AND mobile NOT REGEXP '[^0-9]')
                )
            ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Customer removal needs a separately reviewed data-preserving rollback.');
    }
};
