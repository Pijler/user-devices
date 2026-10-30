<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Pijler\UserDevices\DeviceCreator;
use Pijler\UserDevices\DTO\DeviceContext;
use Pijler\UserDevices\Notifications\AuthenticatedLoginNotification;
use Workbench\App\Models\User;
use Workbench\App\Models\UserDevice;

function loginFrom(User $user, string $ip, string $userAgent, ?string $cookie = null): TestResponse
{
    Auth::forgetGuards();

    $test = test()->withServerVariables(['REMOTE_ADDR' => $ip]);

    if (filled($cookie)) {
        $test->withCookie('user_device', $cookie);
    }

    return $test->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ], ['User-Agent' => $userAgent]);
}

test('it should recognize the device by cookie even when ip and user agent change', function () {
    Notification::fake();

    $user = User::factory()->create(['password' => 'password']);

    $response = loginFrom($user, '10.0.0.1', 'Mozilla/5.0 Chrome/140');

    $token = $response->getCookie('user_device')->getValue();

    loginFrom($user, '10.0.0.2', 'Mozilla/5.0 Chrome/141', $token);

    $devices = $user->userDevices()->get();

    expect($devices)->toHaveCount(1);
    expect($devices->first()->ip_address)->toBe('10.0.0.2');
    expect($devices->first()->user_agent)->toBe('Mozilla/5.0 Chrome/141');
    expect($devices->first()->device_token)->toBe(hash('sha256', $token));

    Notification::assertSentToTimes($user, AuthenticatedLoginNotification::class, 1);
});

test('it should treat a request without the cookie as a new device even with the same ip and user agent', function () {
    Notification::fake();

    $user = User::factory()->create(['password' => 'password']);

    UserDevice::factory()->create([
        'user_id' => $user->id,
        'ip_address' => '10.0.0.1',
        'user_agent' => 'Mozilla/5.0 Common Browser',
        'device_token' => hash('sha256', 'known-token'),
    ]);

    loginFrom($user, '10.0.0.1', 'Mozilla/5.0 Common Browser');

    expect($user->userDevices()->count())->toBe(2);

    Notification::assertSentTo($user, AuthenticatedLoginNotification::class);
});

test('it should adopt a legacy device without token matching ip and user agent', function () {
    Notification::fake();

    $user = User::factory()->create(['password' => 'password']);

    $legacy = UserDevice::factory()->create([
        'user_id' => $user->id,
        'ip_address' => '10.0.0.1',
        'user_agent' => 'Mozilla/5.0 Legacy Browser',
    ]);

    loginFrom($user, '10.0.0.1', 'Mozilla/5.0 Legacy Browser');

    expect($user->userDevices()->count())->toBe(1);
    expect($legacy->fresh()->device_token)->not->toBeNull();

    Notification::assertNothingSent();
});

test('it should block the device identified by cookie', function () {
    $user = User::factory()->create();

    UserDevice::factory()->blocked()->create([
        'user_id' => $user->id,
        'device_token' => hash('sha256', 'blocked-token'),
    ]);

    $this->actingAs($user)
        ->withCookie('user_device', 'blocked-token')
        ->get('/dashboard')
        ->assertStatus(423);
});

test('it should match by user agent only when configured', function () {
    Config::set('user-devices.identify_by', ['user_agent']);

    Notification::fake();

    $user = User::factory()->create(['password' => 'password']);

    loginFrom($user, '10.0.0.1', 'Mozilla/5.0 Same Agent');
    loginFrom($user, '10.0.0.2', 'Mozilla/5.0 Same Agent');

    expect($user->userDevices()->count())->toBe(1);
    expect($user->userDevices()->first()->ip_address)->toBe('10.0.0.2');
    expect($user->userDevices()->first()->device_token)->toBeNull();

    Notification::assertSentToTimes($user, AuthenticatedLoginNotification::class, 1);
});

test('it should match by ip and user agent when configured', function () {
    Config::set('user-devices.identify_by', ['ip_address', 'user_agent']);

    Notification::fake();

    $user = User::factory()->create(['password' => 'password']);

    loginFrom($user, '10.0.0.1', 'Mozilla/5.0 Same Agent');
    loginFrom($user, '10.0.0.2', 'Mozilla/5.0 Same Agent');

    expect($user->userDevices()->count())->toBe(2);

    Notification::assertSentToTimes($user, AuthenticatedLoginNotification::class, 2);
});

test('it should fall back to user agent when the request has no session', function () {
    $context = DeviceContext::fromRequest();

    expect($context->identifyBy)->toBe(['user_agent']);
    expect($context->deviceToken)->toBeNull();
});

test('it should match by location and user agent when configured', function () {
    Config::set('user-devices.identify_by', ['location', 'user_agent']);

    DeviceCreator::resolveLocationUsing(fn (string $ip) => str_starts_with($ip, '10.') ? 'Lisbon, Portugal' : 'Tokyo, Japan');

    Notification::fake();

    $user = User::factory()->create(['password' => 'password']);

    loginFrom($user, '10.0.0.1', 'Mozilla/5.0 Same Agent');
    loginFrom($user, '10.0.0.2', 'Mozilla/5.0 Same Agent');

    expect($user->userDevices()->count())->toBe(1);
    expect($user->userDevices()->first()->ip_address)->toBe('10.0.0.2');

    loginFrom($user, '20.0.0.1', 'Mozilla/5.0 Same Agent');

    expect($user->userDevices()->count())->toBe(2);

    Notification::assertSentToTimes($user, AuthenticatedLoginNotification::class, 2);

    DeviceCreator::$resolveLocation = null;
});

test('it should throw when identify_by has an invalid attribute', function () {
    Config::set('user-devices.identify_by', ['blocked']);

    DeviceContext::fromRequest();
})->throws(InvalidArgumentException::class);

test('it should throw when identify_by is an empty array', function () {
    Config::set('user-devices.identify_by', []);

    DeviceContext::fromRequest();
})->throws(InvalidArgumentException::class);

test('it should throw when identify_by is an unknown string', function () {
    Config::set('user-devices.identify_by', 'user_agent');

    DeviceContext::fromRequest();
})->throws(InvalidArgumentException::class);
