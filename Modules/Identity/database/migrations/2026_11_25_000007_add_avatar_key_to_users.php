<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_key', 40)->charset('ascii')->collation('ascii_bin')->nullable()->default(null)->unique('users_avatar_key_unique');
        });
    }

    public function down(): void
    {
        // Dropping this column loses stored avatar references. Production rollback requires a separate reviewed recovery plan.
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_avatar_key_unique');
            $table->dropColumn('avatar_key');
        });
    }
};
