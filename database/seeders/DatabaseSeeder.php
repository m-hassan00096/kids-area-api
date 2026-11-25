<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Database\Seeders\IdentitySeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(IdentitySeeder::class);
    }
}
