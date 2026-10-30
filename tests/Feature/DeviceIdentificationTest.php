<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Pijler\UserDevices\DeviceCreator;
use Pijler\UserDevices\DTO\DeviceContext;
use Pijler\UserDevices\Exceptions\InvalidIdentifyByException;
use Pijler\UserDevices\Exceptions\MissingLocationResolverException;
use Pijler\UserDevices\Notifications\AuthenticatedLoginNotification;
use Pijler\UserDevices\Notifications\FailedLoginNotification;
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

function useRequest(string $userAgent): void
{
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => $userAgent]);

    Facade::clearResolvedInstance('request');
    app()->instance('request', $request);
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

test('it should treat a request without the cookie as a new device even with the same user agent', function () {
    Notification::fake();

    $user = User::factory()->create(['password' => 'password']);

    UserDevice::factory()->create([
        'user_id' => $user->id,
        'ip_address' => '10.0.0.1',
        'user_agent' => 'Mozilla/5.0 Common Browser',
        'device_token' => hash('sha256', 'known-token'),
    ]);

    loginFrom($user, '10.0.0.2', 'Mozilla/5.0 Common Browser');

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

test('it should not resolve a device token when the request has no session', function () {
    $context = DeviceContext::fromRequest();

    expect($context->identifyBy)->toBe('cookie');
    expect($context->deviceToken)->toBeNull();
});

test('it should only match untokened devices by user agent when the request has no session', function () {
    $user = User::factory()->create();

    $tokened = UserDevice::factory()->create([
        'user_id' => $user->id,
        'user_agent' => 'Mozilla/5.0 Shared Agent',
        'device_token' => hash('sha256', 'laptop-token'),
    ]);

    useRequest('Mozilla/5.0 Shared Agent');

    expect($user->currentDevice()->exists)->toBeFalse();

    $untokened = UserDevice::factory()->create([
        'user_id' => $user->id,
        'user_agent' => 'Mozilla/5.0 Shared Agent',
    ]);

    useRequest('Mozilla/5.0 Shared Agent');

    expect($user->currentDevice()->is($untokened))->toBeTrue();
    expect($tokened->fresh()->device_token)->toBe(hash('sha256', 'laptop-token'));
});

test('it should group repeated failed logins from a client without cookies into one device', function () {
    Notification::fake();

    $user = User::factory()->create(['password' => 'password']);

    foreach (range(1, 3) as $attempt) {
        Auth::forgetGuards();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ], ['User-Agent' => 'Mozilla/5.0 Cookieless Bot']);
    }

    expect($user->userDevices()->count())->toBe(1);

    Notification::assertSentToTimes($user, FailedLoginNotification::class, 1);
});

test('it should not queue the device cookie on read-only blocked checks', function () {
    $user = User::factory()->create();

    $response = $this->get('/test/current-device-blocked?email='.urlencode($user->email));

    expect($response->getCookie('user_device'))->toBeNull();
});

test('it should prefer a blocked device when several devices match', function () {
    Config::set('user-devices.identify_by', ['user_agent']);

    $user = User::factory()->create();

    UserDevice::factory()->blocked()->create([
        'user_id' => $user->id,
        'user_agent' => 'Mozilla/5.0 Duplicated',
        'last_activity' => now()->subDay()->timestamp,
    ]);

    UserDevice::factory()->create([
        'user_id' => $user->id,
        'user_agent' => 'Mozilla/5.0 Duplicated',
        'last_activity' => now()->timestamp,
    ]);

    useRequest('Mozilla/5.0 Duplicated');

    expect($user->currentDevice()->blocked)->toBeTrue();
    expect(UserDevice::where('user_id', $user->id)->isBlocked())->toBeTrue();
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
})->throws(InvalidIdentifyByException::class);

test('it should throw when identify_by is an empty array', function () {
    Config::set('user-devices.identify_by', []);

    DeviceContext::fromRequest();
})->throws(InvalidIdentifyByException::class);

test('it should throw when identify_by is an unknown string', function () {
    Config::set('user-devices.identify_by', 'user_agent');

    DeviceContext::fromRequest();
})->throws(InvalidIdentifyByException::class);

test('it should throw when identify_by has a nested array', function () {
    Config::set('user-devices.identify_by', [['ip_address']]);

    DeviceContext::fromRequest();
})->throws(InvalidIdentifyByException::class);

test('it should not resend the device cookie when the device was used recently', function () {
    $user = User::factory()->create(['password' => 'password']);

    UserDevice::factory()->create([
        'user_id' => $user->id,
        'last_activity' => now()->subHour()->timestamp,
        'device_token' => hash('sha256', 'recent-token'),
    ]);

    $response = loginFrom($user, '10.0.0.1', 'Mozilla/5.0 Recent', 'recent-token');

    expect($response->getCookie('user_device'))->toBeNull();
});

test('it should renew the device cookie once the last use is older than a day', function () {
    $user = User::factory()->create(['password' => 'password']);

    UserDevice::factory()->create([
        'user_id' => $user->id,
        'last_activity' => now()->subDays(2)->timestamp,
        'device_token' => hash('sha256', 'stale-token'),
    ]);

    $response = loginFrom($user, '10.0.0.1', 'Mozilla/5.0 Stale', 'stale-token');

    expect($response->getCookie('user_device')->getValue())->toBe('stale-token');
});

test('it should not send the device cookie when identifying by attributes', function () {
    Config::set('user-devices.identify_by', ['user_agent']);

    $user = User::factory()->create(['password' => 'password']);

    $response = loginFrom($user, '10.0.0.1', 'Mozilla/5.0 Attributes');

    expect($response->getCookie('user_device'))->toBeNull();
});

test('it should not replace the token of a device when a cookieless client matches it', function () {
    $user = User::factory()->create(['password' => 'password']);

    $device = UserDevice::factory()->create([
        'user_id' => $user->id,
        'ip_address' => '10.0.0.1',
        'user_agent' => 'Mozilla/5.0 Office Browser',
        'device_token' => hash('sha256', 'browser-a-token'),
    ]);

    loginFrom($user, '10.0.0.1', 'Mozilla/5.0 Office Browser');

    expect($user->userDevices()->count())->toBe(1);
    expect($device->fresh()->device_token)->toBe(hash('sha256', 'browser-a-token'));
});

test('it should agree with isCurrentDeviceBlocked when the token matches an unblocked device', function () {
    $user = User::factory()->create();

    UserDevice::factory()->blocked()->create([
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0 Shared',
    ]);

    UserDevice::factory()->create([
        'user_id' => $user->id,
        'device_token' => hash('sha256', 'unblocked-token'),
    ]);

    $request = Request::create('/', 'GET', [], ['user_device' => 'unblocked-token'], [], [
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Shared',
    ]);

    $request->setLaravelSession(app('session')->driver());

    Facade::clearResolvedInstance('request');
    $this->instance('request', $request);

    expect($user->isCurrentDeviceBlocked())->toBeFalse();
    expect(UserDevice::where('user_id', $user->id)->isBlocked())->toBeFalse();
});

test('it should throw when identify_by uses location without a resolver', function () {
    Config::set('user-devices.identify_by', ['location', 'user_agent']);

    DeviceContext::fromRequest();
})->throws(MissingLocationResolverException::class);
