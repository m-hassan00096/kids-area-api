<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->engine('InnoDB');
            $table->id();
            $table->string('name', 255);
            $table->string('username', 50)->charset('ascii')->collation('ascii_bin')->unique();
            $table->string('email', 254)->collation('utf8mb4_0900_as_ci')->nullable()->unique();
            $table->string('phone', 11)->charset('ascii')->collation('ascii_bin')->nullable()->unique();
            $table->string('password', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
