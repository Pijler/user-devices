<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use UserDevices\Models\UserDevice;
use Workbench\App\Models\User;

test('it should block device when accessing signed URL', function () {
    $user = User::factory()->create();
    $device = UserDevice::factory()->create(['user_id' => $user->id, 'blocked' => false]);

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
        'user_id' => $user->id,
        'user_agent' => 'Mozilla/5.0 Test Browser',
        'blocked' => false,
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
        'user_id' => $user->id,
        'user_agent' => 'Mozilla/5.0 Blocked Browser',
        'blocked' => true,
    ]);

    $response = $this->actingAs($user)
        ->withHeader('User-Agent', 'Mozilla/5.0 Blocked Browser')
        ->get('/dashboard');

    $response->assertStatus(423);
});
