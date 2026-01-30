<?php

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use UserDevices\DeviceCreator;
use UserDevices\Middleware\CheckCurrentDevice;
use UserDevices\Models\UserDevice;
use Workbench\App\Models\User;

beforeEach(function () {
    DeviceCreator::$userAgent = null;
    DeviceCreator::$userModel = User::class;
    DeviceCreator::$userDeviceModel = UserDevice::class;

    $this->middleware = new CheckCurrentDevice;
    $this->next = fn ($request) => new Response('OK', 200);
});

test('it should proceed to next middleware when device is not blocked', function () {
    $user = User::factory()->create();
    UserDevice::factory()->create([
        'user_id' => $user->id,
        'user_agent' => 'Mozilla/5.0 Test',
        'blocked' => false,
    ]);

    $request = Request::create('/dashboard', 'GET');
    $request->headers->set('User-Agent', 'Mozilla/5.0 Test');
    $request->setUserResolver(fn () => $user);

    $result = $this->middleware->handle($request, $this->next);

    expect($result->getContent())->toBe('OK');
    expect($result->getStatusCode())->toBe(200);
});

test('it should abort with 423 when device is blocked', function () {
    $user = User::factory()->create();
    UserDevice::factory()->create([
        'user_id' => $user->id,
        'user_agent' => 'Mozilla/5.0 Blocked',
        'blocked' => true,
    ]);

    $request = Request::create('/dashboard', 'GET');
    $request->headers->set('User-Agent', 'Mozilla/5.0 Blocked');
    $request->setUserResolver(fn () => $user);

    $this->middleware->handle($request, $this->next);
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);
