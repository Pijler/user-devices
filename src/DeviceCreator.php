<?php

namespace UserDevices;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Request;
use UserDevices\Models\UserDevice;

class DeviceCreator
{
    /**
     * A custom callback used to generate the user agent.
     *
     * If defined, this closure will be executed when generating a new user device,
     * allowing for custom formatting or encoding of the user agent value.
     */
    public static ?Closure $userAgent = null;

    /**
     * The fully qualified class name of the user model.
     *
     * This model is responsible for persisting and managing users in the database.
     * You can override this to use a custom implementation.
     */
    public static string $userModel = 'App\\Models\\User';

    /**
     * The fully qualified class name of the user device model.
     *
     * This model is responsible for persisting and managing user devices
     * in the database. You can override this to use a custom implementation.
     */
    public static string $userDeviceModel = UserDevice::class;

    /**
     * Specify a custom user model class.
     */
    public static function useUserModel(string $model): void
    {
        static::$userModel = $model;
    }

    /**
     * Define a custom callback for generating the user agent.
     */
    public static function userAgentUsing(Closure $callback): void
    {
        static::$userAgent = $callback;
    }

    /**
     * Specify a custom user device model class.
     */
    public static function useUserDeviceModel(string $model): void
    {
        static::$userDeviceModel = $model;
    }

    /**
     * Save a new user device.
     */
    public static function saveUserDevice(bool $ignoreNotification = false): void
    {
        $user = Request::user();

        $device = $user->userDevices()->firstOrNew([
            'ip_address' => Request::ip(),
            'user_agent' => with(Request::userAgent(), static::$userAgent),
        ]);

        tap($device->exists, function ($exists) use ($user, $device, $ignoreNotification) {
            $device->fill([
                'last_activity' => Carbon::now()->timestamp,
            ])->save();

            if (! $exists && ! $ignoreNotification) {
                $user->sendNewLoginDeviceNotification($device);
            }
        });
    }
}
