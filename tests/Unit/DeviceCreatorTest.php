<?php

use Illuminate\Support\Facades\Context;
use UserDevices\DeviceCreator;
use Workbench\App\Models\User;
use Workbench\App\Models\UserDevice;

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

test('it should add ignore notification flag to context', function () {
    DeviceCreator::ignoreNotification();

    expect(Context::get('user_devices.ignore_notification'))->toBeTrue();
});

test('it should set should send notification callback correctly', function () {
    $callback = fn () => true;

    DeviceCreator::shouldSendNotificationUsing($callback);

    expect(DeviceCreator::$shouldSendNotification)->toBe($callback);

    DeviceCreator::$shouldSendNotification = null;
});
