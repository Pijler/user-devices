<?php

use Illuminate\Routing\Route;
use UserDevices\DeviceCreator;
use UserDevices\Http\Requests\BlockDeviceRequest;
use Workbench\App\Models\User;
use Workbench\App\Models\UserDevice;

beforeEach(function () {
    DeviceCreator::$userModel = User::class;

    DeviceCreator::$userDeviceModel = UserDevice::class;
});

test('it should fulfill and block the device', function () {
    $user = User::factory()->create();

    $device = UserDevice::factory()->create([
        'blocked' => false,
        'user_id' => $user->id,
    ]);

    $request = BlockDeviceRequest::create(
        '/devices/block/'.$device->id.'/'.sha1($device->getKey()),
        'GET'
    );

    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setRouteResolver(function () use ($device) {
        $route = new Route('GET', '/devices/block/{id}/{hash}', fn () => null);

        $route->parameters = ['id' => $device->id, 'hash' => sha1($device->getKey())];

        return $route;
    });

    $request->fulfill();

    expect($device->fresh()->blocked)->toBeTrue();
});
