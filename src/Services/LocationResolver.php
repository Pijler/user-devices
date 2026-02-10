<?php

namespace UserDevices\Services;

use UserDevices\DeviceCreator;

class LocationResolver
{
    /**
     * Resolve location (city, country, etc.) from an IP address.
     */
    public static function resolve(string $ip): ?string
    {
        $callback = DeviceCreator::$resolveLocation;

        if (! is_callable($callback)) {
            return null;
        }

        $result = $callback($ip);

        return is_string($result) ? $result : null;
    }
}
