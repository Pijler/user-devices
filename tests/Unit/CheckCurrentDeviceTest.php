<?php

use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        'user_agent' => 'Mozilla/5.0 Test',
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
        'blocked' => true,
        'user_id' => $user->id,
        'user_agent' => 'Mozilla/5.0 Blocked',
    ]);

    $request = Request::create('/dashboard', 'GET');
    $request->headers->set('User-Agent', 'Mozilla/5.0 Blocked');
    $request->setUserResolver(fn () => $user);

    $this->middleware->handle($request, $this->next);
})->throws(HttpException::class);
