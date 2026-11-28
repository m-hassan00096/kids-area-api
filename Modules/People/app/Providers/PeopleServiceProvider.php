<?php

namespace Modules\People\Providers;

use App\Services\Select\SelectRegistry;
use Illuminate\Support\ServiceProvider;
use Modules\People\Services\Select\CustomerOptionsProvider;

final class PeopleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(SelectRegistry::class)->register('customers', new CustomerOptionsProvider);
    }
}
