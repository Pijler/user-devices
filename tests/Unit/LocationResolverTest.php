<?php

use UserDevices\DeviceCreator;
use UserDevices\Services\LocationResolver;

test('it should return null when resolve location is not configured', function () {
    DeviceCreator::$resolveLocation = null;

    expect(LocationResolver::resolve('8.8.8.8'))->toBeNull();
});

test('it should return location when callback is configured', function () {
    DeviceCreator::resolveLocationUsing(fn (string $ip) => "Test City, {$ip}");

    expect(LocationResolver::resolve('8.8.8.8'))->toBe('Test City, 8.8.8.8');

    DeviceCreator::$resolveLocation = null;
});
