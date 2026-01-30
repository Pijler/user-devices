<?php

use Illuminate\Database\Eloquent\Relations\HasMany;
use UserDevices\DeviceCreator;
use UserDevices\Models\UserDevice;
use Workbench\App\Models\User;

beforeEach(function () {
    DeviceCreator::$userModel = User::class;
    DeviceCreator::$userDeviceModel = UserDevice::class;
});

test('it should return hasMany relationship for user devices', function () {
    $user = User::factory()->create();

    $result = $user->userDevices();

    expect($result)->toBeInstanceOf(HasMany::class);
});

test('it should use custom user device model for userDevices relationship', function () {
    DeviceCreator::useUserDeviceModel(UserDevice::class);

    $user = User::factory()->create();

    $result = $user->userDevices();

    expect($result)->toBeInstanceOf(HasMany::class);
});

test('it should send new login device notification', function () {
    \Illuminate\Support\Facades\Notification::fake();

    $user = User::factory()->create();
    $device = UserDevice::factory()->create(['user_id' => $user->id]);

    $user->sendNewLoginDeviceNotification($device);

    \Illuminate\Support\Facades\Notification::assertSentTo($user, \UserDevices\Notifications\NewLoginDeviceNotification::class);
});
