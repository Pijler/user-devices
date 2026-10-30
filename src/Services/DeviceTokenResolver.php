<?php

namespace Pijler\UserDevices\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

class DeviceTokenResolver
{
    /**
     * Resolve the hashed device token from the device cookie.
     *
     * A new token is generated when the cookie is missing. The cookie is queued
     * on every call so its lifetime is renewed while the device stays active.
     */
    public static function resolve(): string
    {
        $request = Request::instance();

        $name = Config::get('user-devices.cookie.name', 'user_device');

        $token = $request->cookies->get($name);

        if (! is_string($token) || blank($token)) {
            $token = Str::random(40);

            // Share the token with later lookups in this same request
            $request->cookies->set($name, $token);
        }

        Cookie::queue($name, $token, (int) Config::get('user-devices.cookie.lifetime', 576000));

        return hash('sha256', $token);
    }
}
