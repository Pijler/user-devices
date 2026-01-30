<?php

use UserDevices\DeviceCreator;
use UserDevices\Http\Requests\BlockDeviceRequest;
use UserDevices\Models\UserDevice;
use Workbench\App\Models\User;

beforeEach(function () {
    DeviceCreator::$userModel = User::class;
    DeviceCreator::$userDeviceModel = UserDevice::class;
});

test('it should fulfill and block the device', function () {
    $user = User::factory()->create();
    $device = UserDevice::factory()->create(['user_id' => $user->id, 'blocked' => false]);

    $request = BlockDeviceRequest::create(
        '/devices/block/'.$device->id.'/'.sha1($device->getKey()),
        'GET'
    );

    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setRouteResolver(function () use ($device) {
        $route = new \Illuminate\Routing\Route('GET', '/devices/block/{id}/{hash}', fn () => null);
        $route->parameters = ['id' => $device->id, 'hash' => sha1($device->getKey())];

        return $route;
    });

    $request->fulfill();

    expect($device->fresh()->blocked)->toBeTrue();
});
