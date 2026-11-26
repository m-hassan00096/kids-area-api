<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (config('permission.teams') || config('permission.table_names.roles') !== 'roles'
            || ! Schema::hasColumn('roles', 'alias_name') || ! Schema::hasColumn('permissions', 'alias_name')) {
            throw new RuntimeException('Unexpected legacy Role alias schema; review migration prerequisites.');
        }

        // Forward only. Existing role names, IDs and every pivot remain authoritative.
        // Back up and review legacy role aliases before an authorized real deployment.
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('alias_name');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Role alias removal is not reversible without the reviewed pre-migration backup.');
    }
};
