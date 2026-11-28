<?php

namespace Modules\Identity\Providers;

use App\Services\Select\SelectRegistry;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Console\BootstrapManagerCommand;
use Modules\Identity\Console\ProvisionActivityCatalogueCommand;
use Modules\Identity\Console\ProvisionCustomerCatalogueCommand;
use Modules\Identity\Services\Select\PermissionOptionsProvider;
use Modules\Identity\Services\Select\RoleOptionsProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The browser transport has no personal-access-token fallback.
        Sanctum::getAccessTokenFromRequestUsing(static fn () => null);

        $registry = $this->app->make(SelectRegistry::class);
        $registry->register('roles', new RoleOptionsProvider(assignment: true));
        $registry->register('userFilterRoles', new RoleOptionsProvider(assignment: false));
        $registry->register('permissions', new PermissionOptionsProvider);

        if ($this->app->runningInConsole()) {
            $this->commands([BootstrapManagerCommand::class, ProvisionActivityCatalogueCommand::class, ProvisionCustomerCatalogueCommand::class]);
        }
    }
}
