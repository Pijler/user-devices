<?php

namespace UserDevices;

use Closure;
use Illuminate\Support\Facades\Context;
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
     * A custom callback to determine whether to send the new login device notification.
     *
     * When set, this closure receives the user and device and returns a boolean.
     * If it returns false, the notification is not sent. When null, notifications
     * are sent by default (unless ignoreNotification() was called for the request).
     */
    public static ?Closure $shouldSendNotification = null;

    /**
     * The fully qualified class name of the user device model.
     *
     * This model is responsible for persisting and managing user devices
     * in the database. You can override this to use a custom implementation.
     */
    public static string $userDeviceModel = UserDevice::class;

    /**
     * Add a flag to the request context to ignore the new login device notification.
     *
     * Call this before authentication (e.g. in middleware or controller) when you
     * want to save the device but skip sending the notification email.
     */
    public static function ignoreNotification(): void
    {
        Context::add('user_devices.ignore_notification', true);
    }

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
     * Set a callback to determine whether to send the new login device notification.
     *
     * The callback receives the user and device as arguments and should return a boolean.
     */
    public static function shouldSendNotificationUsing(Closure $callback): void
    {
        static::$shouldSendNotification = $callback;
    }
}
