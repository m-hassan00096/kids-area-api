<?php

namespace App\Providers;

use App\Session\PrivateDatabaseSessionHandler;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Laravel's SessionManager still builds the configured EncryptedStore.
        Session::extend('database', static fn ($app) => new PrivateDatabaseSessionHandler(
            $app['db']->connection(config('session.connection')),
            config('session.table'),
            config('session.lifetime'),
            $app,
        ));
    }
}
