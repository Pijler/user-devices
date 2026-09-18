<?php

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Pijler\UserDevices\DeviceCreator;
use Pijler\UserDevices\Services\LocationResolver;

test('it should return null when resolve location is not configured', function () {
    DeviceCreator::$resolveLocation = null;

    expect(LocationResolver::resolve('8.8.8.8'))->toBeNull();
});

test('it should return location when callback is configured', function () {
    DeviceCreator::resolveLocationUsing(fn (string $ip) => "Test City, {$ip}");

    expect(LocationResolver::resolve('8.8.8.8'))->toBe('Test City, 8.8.8.8');

    DeviceCreator::$resolveLocation = null;
});

test('it should resolve location only once for the same ip', function () {
    $calls = 0;

    DeviceCreator::resolveLocationUsing(function (string $ip) use (&$calls) {
        $calls++;

        return "Test City, {$ip}";
    });

    $this->session([]);
    Request::instance()->setLaravelSession(Session::driver());

    expect(LocationResolver::resolve('8.8.8.8'))->toBe('Test City, 8.8.8.8');
    expect(LocationResolver::resolve('8.8.8.8'))->toBe('Test City, 8.8.8.8');
    expect($calls)->toBe(1);

    DeviceCreator::$resolveLocation = null;
});

test('it should resolve location again for a different ip', function () {
    $calls = 0;

    DeviceCreator::resolveLocationUsing(function (string $ip) use (&$calls) {
        $calls++;

        return "Test City, {$ip}";
    });

    $this->session([]);
    Request::instance()->setLaravelSession(Session::driver());

    LocationResolver::resolve('8.8.8.8');
    LocationResolver::resolve('1.1.1.1');

    expect($calls)->toBe(2);

    DeviceCreator::$resolveLocation = null;
});

test('it should cache a null location so the provider is not called again', function () {
    $calls = 0;

    DeviceCreator::resolveLocationUsing(function () use (&$calls) {
        $calls++;

        return null;
    });

    $this->session([]);
    Request::instance()->setLaravelSession(Session::driver());

    expect(LocationResolver::resolve('4.4.4.4'))->toBeNull();
    expect(LocationResolver::resolve('4.4.4.4'))->toBeNull();
    expect($calls)->toBe(1);

    DeviceCreator::$resolveLocation = null;
});
