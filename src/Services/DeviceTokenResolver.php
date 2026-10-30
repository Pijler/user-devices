<?php

namespace Pijler\UserDevices\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

class DeviceTokenResolver
{
    /**
     * The request attribute flagging a token generated during this request.
     */
    private const GENERATED_ATTRIBUTE = 'user_devices.generated_device_token';

    /**
     * Resolve the hashed device token from the device cookie.
     *
     * A new token is generated when the cookie is missing and shared
     * with later lookups in this same request.
     */
    public static function resolve(): string
    {
        $request = Request::instance();

        $token = $request->cookies->get(static::cookieName());

        if (! is_string($token) || blank($token)) {
            $token = Str::random(40);

            $request->cookies->set(static::cookieName(), $token);

            $request->attributes->set(self::GENERATED_ATTRIBUTE, true);
        }

        return hash('sha256', $token);
    }

    /**
     * Determine whether the device token was generated during this request.
     */
    public static function isGenerated(): bool
    {
        return Request::instance()->attributes->get(self::GENERATED_ATTRIBUTE, false);
    }

    /**
     * Queue the device cookie, renewing its lifetime.
     *
     * Requires the AddQueuedCookiesToResponse middleware (included in the "web" group).
     */
    public static function queue(): void
    {
        $token = Request::instance()->cookies->get(static::cookieName());

        if (! is_string($token) || blank($token)) {
            return;
        }

        Cookie::queue(Cookie::make(
            value: $token,
            name: static::cookieName(),
            minutes: (int) Config::get('user-devices.cookie.lifetime', 259200),
        ));
    }

    /**
     * Get the device cookie name.
     */
    private static function cookieName(): string
    {
        return Config::get('user-devices.cookie.name', 'user_device');
    }
}
