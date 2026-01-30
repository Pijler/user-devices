<?php

use UserDevices\DeviceCreator;
use UserDevices\Models\UserDevice;
use Workbench\App\Models\User;

beforeEach(function () {
    DeviceCreator::$userAgent = null;
    DeviceCreator::$userModel = User::class;
    DeviceCreator::$userDeviceModel = UserDevice::class;
});

test('it should have default user model', function () {
    expect(DeviceCreator::$userModel)->toBe(User::class);
});

test('it should have default user device model', function () {
    expect(DeviceCreator::$userDeviceModel)->toBe(UserDevice::class);
});

test('it should have default user agent callback as null', function () {
    expect(DeviceCreator::$userAgent)->toBeNull();
});

test('it should set user model correctly', function () {
    $customModel = 'Custom\\User';

    DeviceCreator::useUserModel($customModel);

    expect(DeviceCreator::$userModel)->toBe($customModel);
});

test('it should set user device model correctly', function () {
    $customModel = 'Custom\\UserDevice';

    DeviceCreator::useUserDeviceModel($customModel);

    expect(DeviceCreator::$userDeviceModel)->toBe($customModel);
});

test('it should set user agent callback correctly', function () {
    $callback = fn ($ua) => substr($ua ?? '', 0, 100);

    DeviceCreator::userAgentUsing($callback);

    expect(DeviceCreator::$userAgent)->toBe($callback);
});
