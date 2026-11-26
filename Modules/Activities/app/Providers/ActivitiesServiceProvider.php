<?php

namespace Modules\Activities\Providers;

use App\Services\Select\SelectRegistry;
use Illuminate\Support\ServiceProvider;
use Modules\Activities\Services\Select\ActivityOptionsProvider;

final class ActivitiesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $registry = $this->app->make(SelectRegistry::class);
        $registry->register('activities', new ActivityOptionsProvider(activeOnly: false));
        $registry->register('activeActivities', new ActivityOptionsProvider(activeOnly: true));
    }
}
