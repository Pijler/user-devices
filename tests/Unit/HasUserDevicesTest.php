<?php

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Notification;
use UserDevices\DeviceCreator;
use UserDevices\Notifications\NewLoginDeviceNotification;
use Workbench\App\Models\User;
use Workbench\App\Models\UserDevice;

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
    Notification::fake();

    $user = User::factory()->create();
    $device = UserDevice::factory()->create(['user_id' => $user->id]);

    $user->sendNewLoginDeviceNotification($device);

    Notification::assertSentTo($user, NewLoginDeviceNotification::class);
});
