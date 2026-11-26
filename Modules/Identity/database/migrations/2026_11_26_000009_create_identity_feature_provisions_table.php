<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Schema only. Grant changes and the completion marker must later be
        // committed together by the explicitly approved Identity provisioner.
        DB::statement(<<<'SQL'
            CREATE TABLE identity_feature_provisions (
                feature_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
                completed_at TIMESTAMP NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Provision completion evidence must not be dropped by an automatic rollback.');
    }
};
