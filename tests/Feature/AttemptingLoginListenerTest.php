<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use UserDevices\Notifications\AttemptingLoginNotification;
use Workbench\App\Models\User;

beforeEach(function () {
    Config::set('user-devices.events.attempting', true);
});

afterEach(function () {
    Config::set('user-devices.events.attempting', false);
});

test('it should save device when login is attempted', function () {
    $user = User::factory()->create(['password' => 'password']);

    expect($user->userDevices)->toHaveCount(0);

    $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.100'])->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ], ['User-Agent' => 'Mozilla/5.0 Attempt Test Browser']);

    $user->refresh();
    $device = $user->userDevices->first();

    expect($user->userDevices)->toHaveCount(1);
    expect($device->ip_address)->toBe('192.168.1.100');
    expect($device->user_agent)->toBe('Mozilla/5.0 Attempt Test Browser');
});

test('it should send attempting login notification for new device', function () {
    Notification::fake();

    $user = User::factory()->create(['password' => 'password']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ], ['User-Agent' => 'Mozilla/5.0 New Device For Attempt']);

    Notification::assertSentTo($user, AttemptingLoginNotification::class);
});

test('it should not send attempting login notification for existing device', function () {
    Notification::fake();

    $user = User::factory()->create(['password' => 'password']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ], ['User-Agent' => 'Mozilla/5.0 Same Device']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ], ['User-Agent' => 'Mozilla/5.0 Same Device']);

    Notification::assertSentToTimes($user, AttemptingLoginNotification::class, 1);
});
