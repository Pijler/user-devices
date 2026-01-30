<?php

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\UserDevice;

test('it should have correct guarded attributes', function () {
    $model = new UserDevice;

    expect($model->getGuarded())->toBe([]);
});

test('it should have correct casts', function () {
    $model = new UserDevice;

    $casts = $model->getCasts();

    expect($casts)->toHaveKey('blocked', 'boolean');
    expect($casts)->toHaveKey('last_activity', 'timestamp');
});

test('it should return belongsTo relationship for user', function () {
    $model = new UserDevice;

    expect($model->user())->toBeInstanceOf(BelongsTo::class);
});

test('it should block the device', function () {
    $device = UserDevice::factory()->create(['blocked' => false]);

    $device->block();

    expect($device->fresh()->blocked)->toBeTrue();
});

test('it should unblock the device', function () {
    $device = UserDevice::factory()->create(['blocked' => true]);

    $device->unblock();

    expect($device->fresh()->blocked)->toBeFalse();
});

test('it should mark device as blocked statically', function () {
    $device = UserDevice::factory()->create(['blocked' => false]);

    UserDevice::markAsBlocked($device->id);

    expect($device->fresh()->blocked)->toBeTrue();
});

test('it should mark device as unblocked statically', function () {
    $device = UserDevice::factory()->create(['blocked' => true]);

    UserDevice::markAsUnblocked($device->id);

    expect($device->fresh()->blocked)->toBeFalse();
});
