<?php

use Illuminate\Support\Facades\Route;
use UserDevices\Http\Requests\BlockDeviceRequest;

Route::middleware(['auth', 'check.device'])->group(function () {
    Route::get('/dashboard', function () {
        return response()->json(['message' => 'Access granted']);
    });
});

Route::get('/devices/block/{id}/{hash}', function (BlockDeviceRequest $request) {
    $request->fulfill();

    return redirect('/')->with('message', 'Device blocked successfully.');
})->middleware(['signed', 'throttle:6,1'])->name('user-devices.block');
