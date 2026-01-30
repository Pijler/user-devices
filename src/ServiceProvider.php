<?php

namespace UserDevices;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use UserDevices\Middleware\CheckCurrentDevice;

class ServiceProvider extends LaravelServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootMigrations();

        $this->bootMiddleware();
    }

    /**
     * Publish the package migrations to the application's migrations directory.
     */
    private function bootMigrations(): void
    {
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'user-devices-migrations');
    }

    /**
     * Register the package middleware in the Laravel router.
     *
     * The middleware is registered under an alias, which can be used
     * directly in routes or middleware groups.
     */
    private function bootMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('check-device', CheckCurrentDevice::class);
    }
}
