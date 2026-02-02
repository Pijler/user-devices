<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use UserDevices\DeviceCreator;
use UserDevices\Notifications\NewLoginDeviceNotification;
use Workbench\App\Models\User;
use Workbench\App\Models\UserDevice;

test('it should save user device when authenticated', function () {
    $user = User::factory()->create();

    expect($user->userDevices)->toHaveCount(0);

    $this->actingAs($user)->get('/dashboard');

    $user->refresh();
    expect($user->userDevices)->toHaveCount(1);
});

test('it should not send notification when ignoreNotification is called via context', function () {
    Notification::fake();

    $user = User::factory()->create();

    DeviceCreator::ignoreNotification();

    $this->actingAs($user)
        ->withHeader('User-Agent', 'Mozilla/5.0 Silent Device Browser')
        ->get('/dashboard');

    Notification::assertNothingSent();
});

test('it should send notification for new device when context is not set', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeader('User-Agent', 'Mozilla/5.0 Brand New Device')
        ->get('/dashboard');

    Notification::assertSentTo($user, NewLoginDeviceNotification::class);
});

test('it should not send notification when shouldSendNotificationUsing returns false', function () {
    Notification::fake();

    $user = User::factory()->create();

    DeviceCreator::shouldSendNotificationUsing(fn () => false);

    $this->actingAs($user)
        ->withHeader('User-Agent', 'Mozilla/5.0 Yet Another New Device')
        ->get('/dashboard');

    Notification::assertNothingSent();

    DeviceCreator::$shouldSendNotification = null;
});

test('it should send notification when shouldSendNotificationUsing returns true', function () {
    Notification::fake();

    $user = User::factory()->create();

    DeviceCreator::shouldSendNotificationUsing(fn () => true);

    $this->actingAs($user)
        ->withHeader('User-Agent', 'Mozilla/5.0 Custom Callback Device')
        ->get('/dashboard');

    Notification::assertSentTo($user, NewLoginDeviceNotification::class);

    DeviceCreator::$shouldSendNotification = null;
});

test('it should block device when accessing signed URL', function () {
    $user = User::factory()->create();

    $device = UserDevice::factory()->create([
        'blocked' => false,
        'user_id' => $user->id,
    ]);

    $url = URL::temporarySignedRoute(
        'user-devices.block',
        Carbon::now()->addMinutes(60),
        [
            'id' => $device->id,
            'hash' => sha1($device->getKey()),
        ]
    );

    $response = $this->get($url);

    $response->assertRedirect();
    expect($device->fresh()->blocked)->toBeTrue();
});

test('it should return 403 when hash is invalid', function () {
    $user = User::factory()->create();

    $device = UserDevice::factory()->create(['user_id' => $user->id]);

    $url = URL::temporarySignedRoute(
        'user-devices.block',
        Carbon::now()->addMinutes(60),
        [
            'id' => $device->id,
            'hash' => 'invalid-hash',
        ]
    );

    $response = $this->get($url);

    $response->assertForbidden();
    expect($device->fresh()->blocked)->toBeFalse();
});

test('it should return 403 when signature is invalid', function () {
    $user = User::factory()->create();

    $device = UserDevice::factory()->create(['user_id' => $user->id]);

    $url = route('user-devices.block', [
        'id' => $device->id,
        'hash' => sha1($device->getKey()),
    ]).'&signature=invalid&expires='.time();

    $response = $this->get($url);

    $response->assertStatus(403);
});

test('it should access dashboard when device is not blocked', function () {
    $user = User::factory()->create();

    UserDevice::factory()->create([
        'blocked' => false,
        'user_id' => $user->id,
        'user_agent' => 'Mozilla/5.0 Test Browser',
    ]);

    $response = $this->actingAs($user)
        ->withHeader('User-Agent', 'Mozilla/5.0 Test Browser')
        ->get('/dashboard');

    $response->assertOk();
    $response->assertJson(['message' => 'Access granted']);
});

test('it should return 423 when device is blocked', function () {
    $user = User::factory()->create();

    UserDevice::factory()->create([
        'blocked' => true,
        'user_id' => $user->id,
        'user_agent' => 'Mozilla/5.0 Blocked Browser',
    ]);

    $response = $this->actingAs($user)
        ->withHeader('User-Agent', 'Mozilla/5.0 Blocked Browser')
        ->get('/dashboard');

    $response->assertStatus(423);
});
