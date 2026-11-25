<?php

namespace Modules\Identity\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Identity\Console\BootstrapManagerCommand;

class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([BootstrapManagerCommand::class]);
        }
    }
}
