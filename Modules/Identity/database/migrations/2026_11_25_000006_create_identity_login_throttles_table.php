<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_login_throttles', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->unsignedTinyInteger('scope');
            $table->char('key_digest', 64)->charset('ascii')->collation('ascii_bin');
            $table->json('failure_times');
            $table->unsignedBigInteger('blocked_until_ms')->nullable();
            $table->unsignedBigInteger('expires_at_ms')->index();
            $table->primary(['scope', 'key_digest']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_login_throttles');
    }
};
