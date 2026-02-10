<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use UserDevices\DeviceCreator;
use UserDevices\Notifications\AuthenticatedLoginNotification;
use Workbench\App\Models\User;
use Workbench\App\Models\UserDevice;

test('it should save user device when authenticated', function () {
    $user = User::factory()->create();

    expect($user->userDevices)->toHaveCount(0);

    $this->actingAs($user)->get('/dashboard');

    $user->refresh();
    expect($user->userDevices)->toHaveCount(1);
});

test('it should not save user device when ignoreListener is called via context', function () {
    $user = User::factory()->create();

    expect($user->userDevices)->toHaveCount(0);

    DeviceCreator::ignoreListener();

    $this->actingAs($user)->get('/dashboard');

    $user->refresh();
    expect($user->userDevices)->toHaveCount(0);
});

test('it should not send notification when ignoreNotification is called via context', function () {
    Notification::fake();

    $user = User::factory()->create();

    DeviceCreator::ignoreNotification();

    $this->actingAs($user)->get('/dashboard', [
        'User-Agent' => 'Mozilla/5.0 Silent Device Browser',
    ]);

    Notification::assertNothingSent();
});

test('it should send notification for new device when context is not set', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard', [
        'User-Agent' => 'Mozilla/5.0 Brand New Device',
    ]);

    Notification::assertSentTo($user, AuthenticatedLoginNotification::class);
});

test('it should not send notification when shouldSendNotificationUsing returns false', function () {
    Notification::fake();

    $user = User::factory()->create();

    DeviceCreator::shouldSendNotificationUsing(fn () => false);

    $this->actingAs($user)->get('/dashboard', [
        'User-Agent' => 'Mozilla/5.0 Yet Another New Device',
    ]);

    Notification::assertNothingSent();

    DeviceCreator::$shouldSendNotification = null;
});

test('it should send notification when shouldSendNotificationUsing returns true', function () {
    Notification::fake();

    $user = User::factory()->create();

    DeviceCreator::shouldSendNotificationUsing(fn () => true);

    $this->actingAs($user)->get('/dashboard', [
        'User-Agent' => 'Mozilla/5.0 Custom Callback Device',
    ]);

    Notification::assertSentTo($user, AuthenticatedLoginNotification::class);

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
        name: 'user-devices.block',
        expiration: Carbon::now()->addMinutes(60),
        parameters: [
            'id' => $device->id,
            'hash' => 'invalid-hash',
        ],
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

    $response = $this->actingAs($user)->get('/dashboard', [
        'User-Agent' => 'Mozilla/5.0 Test Browser',
    ]);

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

    $response = $this->actingAs($user)->get('/dashboard', [
        'User-Agent' => 'Mozilla/5.0 Blocked Browser',
    ]);

    $response->assertStatus(423);
});
