<?php

use UserDevices\DeviceCreator;
use Workbench\App\Models\User;
use Workbench\App\Models\UserDevice;

test('it should save location when resolveLocationUsing is configured', function () {
    DeviceCreator::resolveLocationUsing(fn (string $ip) => $ip === '127.0.0.1' ? 'Localhost, Test' : null);

    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard', [
        'User-Agent' => 'Mozilla/5.0 Location Test',
    ]);

    $device = $user->userDevices()->first();

    expect($device)->not->toBeNull();
    expect($device->location)->toBe('Localhost, Test');

    DeviceCreator::$resolveLocation = null;
});

test('it should block device with session_id without throwing', function () {
    $user = User::factory()->create();

    $device = UserDevice::factory()->create([
        'user_id' => $user->id,
        'session_id' => 'some-session-id',
    ]);

    $device->block();

    expect($device->fresh()->blocked)->toBeTrue();
});
