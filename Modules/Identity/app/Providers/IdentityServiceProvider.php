<?php

namespace Modules\Identity\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Console\BootstrapManagerCommand;

class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The browser transport has no personal-access-token fallback.
        Sanctum::getAccessTokenFromRequestUsing(static fn () => null);

        if ($this->app->runningInConsole()) {
            $this->commands([BootstrapManagerCommand::class]);
        }
    }
}
