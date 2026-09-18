<?php

namespace Pijler\UserDevices\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Request;
use Pijler\UserDevices\DeviceCreator;

class LocationResolver
{
    /**
     * Resolve location (city, country, etc.) from an IP address.
     */
    public static function resolve(string $ip): ?string
    {
        $callback = DeviceCreator::$resolveLocation;

        if (blank($ip) || ! is_callable($callback)) {
            return null;
        }

        $request = Request::instance();

        if ($request->hasSession() && method_exists($request->session(), 'cache')) {
            return static::remember($callback, $ip);
        }

        return static::fromCallback($callback, $ip);
    }

    /**
     * Resolve the location using the configured callback.
     */
    private static function fromCallback(callable $callback, string $ip): ?string
    {
        $result = $callback($ip);

        return is_string($result) ? $result : null;
    }

    /**
     * Remember the location for this IP in the current session cache.
     */
    private static function remember(callable $callback, string $ip): ?string
    {
        $location = Request::instance()->session()->cache()->remember(
            key: "user_devices.location.{$ip}",
            callback: fn () => static::fromCallback($callback, $ip) ?? '',
            ttl: Carbon::now()->addMinutes((int) config('session.lifetime', 120)),
        );

        return blank($location) ? null : $location;
    }
}
