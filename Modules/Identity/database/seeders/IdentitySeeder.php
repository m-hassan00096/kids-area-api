<?php

namespace Modules\Identity\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Actions\SeedIdentityCatalogueAction;

class IdentitySeeder extends Seeder
{
    public function run(SeedIdentityCatalogueAction $action): void
    {
        $action->execute();
    }
}
