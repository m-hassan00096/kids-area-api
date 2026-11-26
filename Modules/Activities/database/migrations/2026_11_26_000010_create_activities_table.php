<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection();
        $server = $connection->selectOne('SELECT VERSION() AS version, @@SESSION.sql_mode AS sql_mode, TIMEDIFF(NOW(), UTC_TIMESTAMP()) AS utc_offset');
        if ($connection->getDriverName() !== 'mysql'
            || preg_match('/\A8\.[0-9]+\.[0-9]+/', $server->version, $version) !== 1
            || version_compare($version[0], '8.0.16', '<')
            || (! str_contains($server->sql_mode, 'STRICT_TRANS_TABLES') && ! str_contains($server->sql_mode, 'STRICT_ALL_TABLES'))
            || $server->utc_offset !== '00:00:00') {
            throw new RuntimeException('Activities requires reviewed MySQL 8 CHECK enforcement, strict SQL mode and UTC.');
        }

        // A single CREATE makes every approved column, unique index and CHECK
        // part of the same MySQL DDL operation. No separate ALTER or data seed.
        $connection->statement(<<<'SQL'
            CREATE TABLE activities (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_ci NOT NULL,
                supports_hourly TINYINT(1) NOT NULL,
                supports_subscription TINYINT(1) NOT NULL,
                hourly_rate DECIMAL(12,2) NULL DEFAULT NULL,
                monthly_price DECIMAL(12,2) NULL DEFAULT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY uq_activities_name (name),
                CONSTRAINT chk_activities_flags CHECK (supports_hourly IN (0,1) AND supports_subscription IN (0,1) AND is_active IN (0,1)),
                CONSTRAINT chk_activities_billing CHECK (supports_hourly = 1 OR supports_subscription = 1),
                CONSTRAINT chk_activities_hourly_rate CHECK ((supports_hourly = 1 AND hourly_rate IS NOT NULL AND hourly_rate > 0) OR (supports_hourly = 0 AND hourly_rate IS NULL)),
                CONSTRAINT chk_activities_monthly_price CHECK ((supports_subscription = 1 AND monthly_price IS NOT NULL AND monthly_price > 0) OR (supports_subscription = 0 AND monthly_price IS NULL)),
                CONSTRAINT chk_activities_name CHECK (CHAR_LENGTH(TRIM(name)) > 0)
            ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Activity catalogue removal needs a separately reviewed data-preserving rollback.');
    }
};
