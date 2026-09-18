<?php

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Notification;
use Pijler\UserDevices\DeviceCreator;
use Pijler\UserDevices\Notifications\AuthenticatedLoginNotification;
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

    $user->sendAuthenticatedLoginNotification($device);

    Notification::assertSentTo($user, AuthenticatedLoginNotification::class);
});

test('it should memoize current device for the same ip and user agent', function () {
    $user = User::factory()->create();

    $request = Request::create('/', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.8',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Memo',
    ]);

    Facade::clearResolvedInstance('request');
    $this->instance('request', $request);

    UserDevice::factory()->create([
        'user_id' => $user->id,
        'ip_address' => '10.0.0.8',
        'user_agent' => 'Mozilla/5.0 Memo',
    ]);

    expect($user->currentDevice())->toBe($user->currentDevice());
});

test('it should query user devices only once when resolving current device and checking blocked', function () {
    $user = User::factory()->create();

    $request = Request::create('/', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.9',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Query Once',
    ]);

    Facade::clearResolvedInstance('request');
    $this->instance('request', $request);

    UserDevice::factory()->create([
        'blocked' => true,
        'user_id' => $user->id,
        'ip_address' => '10.0.0.9',
        'user_agent' => 'Mozilla/5.0 Query Once',
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $device = $user->currentDevice();

    expect($device->exists)->toBeTrue();
    expect($device->blocked)->toBeTrue();
    expect($user->currentDevice()->blocked)->toBeTrue();

    $deviceQueries = collect(DB::getQueryLog())->filter(function (array $query) {
        return str_contains($query['query'], 'user_devices');
    });

    expect($deviceQueries)->toHaveCount(1);
});

test('it should keep current devices with dotted ips and user agents as distinct keys', function () {
    $user = User::factory()->create();

    $request = Request::create('/', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.8',
        'HTTP_USER_AGENT' => 'Mozilla/5.0',
    ]);

    Facade::clearResolvedInstance('request');
    $this->instance('request', $request);

    $first = $user->currentDevice();

    $request = Request::create('/', 'GET', [], [], [], [
        'REMOTE_ADDR' => '10.0.0.8',
        'HTTP_USER_AGENT' => 'Mozilla/5.0.Extra',
    ]);

    Facade::clearResolvedInstance('request');
    $this->instance('request', $request);

    $second = $user->currentDevice();

    expect($first)->not->toBe($second);
    expect($user->currentDevice())->toBe($second);
});
