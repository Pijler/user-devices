<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use UserDevices\Notifications\FailedLoginNotification;
use Workbench\App\Models\User;

beforeEach(function () {
    Config::set('user-devices.events.failed', true);
});

afterEach(function () {
    Config::set('user-devices.events.failed', true);
});

test('it should save device when login fails', function () {
    $user = User::factory()->create(['password' => 'password']);

    expect($user->userDevices)->toHaveCount(0);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ], ['User-Agent' => 'Mozilla/5.0 Failed Test Browser']);

    $user->refresh();
    $device = $user->userDevices->first();

    expect($user->userDevices)->toHaveCount(1);
    expect($device->user_agent)->toBe('Mozilla/5.0 Failed Test Browser');
});

test('it should send failed login notification for new device', function () {
    Notification::fake();

    $user = User::factory()->create(['password' => 'password']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ], ['User-Agent' => 'Mozilla/5.0 New Failed Device']);

    Notification::assertSentTo($user, FailedLoginNotification::class);
});

test('it should not send failed login notification for existing device', function () {
    Notification::fake();

    $user = User::factory()->create(['password' => 'password']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong',
    ], ['User-Agent' => 'Mozilla/5.0 Repeated Failed Device']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong',
    ], ['User-Agent' => 'Mozilla/5.0 Repeated Failed Device']);

    Notification::assertSentToTimes($user, FailedLoginNotification::class, 1);
});
