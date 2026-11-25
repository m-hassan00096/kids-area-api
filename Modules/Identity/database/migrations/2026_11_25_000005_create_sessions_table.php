<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->string('id', 40)->charset('ascii')->collation('ascii_bin')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->unsignedInteger('last_activity')->index();
            // Disposable authentication state: intentionally no FK to users.
            $table->unsignedBigInteger('auth_user_id')->nullable();
            $table->unsignedBigInteger('auth_version')->nullable();
            $table->unsignedBigInteger('auth_last_activity')->nullable();
            $table->index(['auth_user_id', 'id'], 'sessions_auth_owner_index');
        });

        DB::statement('ALTER TABLE sessions ADD CONSTRAINT sessions_auth_binding_check CHECK ((auth_user_id IS NULL AND auth_version IS NULL AND auth_last_activity IS NULL) OR (auth_user_id IS NOT NULL AND auth_version IS NOT NULL AND auth_last_activity IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
