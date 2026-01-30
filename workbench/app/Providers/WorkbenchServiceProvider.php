<?php

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use UserDevices\DeviceCreator;
use Workbench\App\Models\User;
use Workbench\App\Models\UserDevice;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        DeviceCreator::useUserModel(User::class);

        DeviceCreator::useUserDeviceModel(UserDevice::class);
    }
}
