<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('permission.table_names.model_has_roles');
        $morphKey = config('permission.column_names.model_morph_key');

        if (config('permission.teams') || ! is_string($tableName) || ! is_string($morphKey)) {
            throw new RuntimeException('Identity single-role migration requires the approved non-Team Spatie schema.');
        }

        $duplicates = DB::table($tableName)
            ->select('model_type', $morphKey)
            ->groupBy('model_type', $morphKey)
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException('Existing multiple role assignments require reviewed correction before migration.');
        }

        Schema::table($tableName, function (Blueprint $table) use ($morphKey): void {
            $table->unique(['model_type', $morphKey], 'identity_single_role_unique');
        });
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.model_has_roles'), function (Blueprint $table): void {
            $table->dropUnique('identity_single_role_unique');
        });
    }
};
