<?php

namespace Pijler\UserDevices;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Failed;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Pijler\UserDevices\Listeners\AttemptingLoginListener;
use Pijler\UserDevices\Listeners\AuthenticatedLoginListener;
use Pijler\UserDevices\Listeners\FailedLoginListener;
use Pijler\UserDevices\Middleware\CheckCurrentDevice;

class ServiceProvider extends LaravelServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootConfig();

        $this->bootMigrations();

        $this->bootMiddleware();

        $this->bootEventListeners();
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/user-devices.php', 'user-devices');
    }

    /**
     * Publish the package config file.
     */
    private function bootConfig(): void
    {
        $this->publishes([
            __DIR__.'/../config/user-devices.php' => config_path('user-devices.php'),
        ], 'user-devices-config');
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

        $router->aliasMiddleware('check.device', CheckCurrentDevice::class);
    }

    /**
     * Register the package event listeners.
     */
    private function bootEventListeners(): void
    {
        Event::listen(Failed::class, FailedLoginListener::class);

        Event::listen(Attempting::class, AttemptingLoginListener::class);

        Event::listen(Authenticated::class, AuthenticatedLoginListener::class);
    }
}
