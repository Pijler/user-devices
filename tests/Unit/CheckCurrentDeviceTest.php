<?php

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;
use Symfony\Component\HttpKernel\Exception\HttpException;
use UserDevices\Middleware\CheckCurrentDevice;
use Workbench\App\Models\User;
use Workbench\App\Models\UserDevice;

beforeEach(function () {
    $this->middleware = new CheckCurrentDevice;

    $this->next = fn ($request) => new Response('OK', 200);
});

test('it should proceed to next middleware when device is not blocked', function () {
    $user = User::factory()->create();

    UserDevice::factory()->create([
        'blocked' => false,
        'user_id' => $user->id,
        'ip_address' => '192.168.1.100',
        'user_agent' => 'Mozilla/5.0 Test',
    ]);

    Auth::guard('web')->setUser($user);

    $request = Request::create('/dashboard', 'GET', [], [], [], [
        'REMOTE_ADDR' => '192.168.1.100',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Test',
    ]);
    $request->setUserResolver(fn () => Auth::guard('web')->user());

    Facade::clearResolvedInstance('request');
    $this->instance('request', $request);

    $result = $this->middleware->handle($request, $this->next);

    expect($result->getContent())->toBe('OK');
    expect($result->getStatusCode())->toBe(200);
});

test('it should abort with 423 when device is blocked', function () {
    $user = User::factory()->create();

    UserDevice::factory()->create([
        'blocked' => true,
        'user_id' => $user->id,
        'ip_address' => '192.168.1.100',
        'user_agent' => 'Mozilla/5.0 Blocked',
    ]);

    Auth::guard('web')->setUser($user);

    $request = Request::create('/dashboard', 'GET', [], [], [], [
        'REMOTE_ADDR' => '192.168.1.100',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Blocked',
    ]);
    $request->setUserResolver(fn () => Auth::guard('web')->user());

    Facade::clearResolvedInstance('request');
    $this->instance('request', $request);

    expect(function () use ($request) {
        $this->middleware->handle($request, $this->next);
    })->toThrow(HttpException::class);
});
