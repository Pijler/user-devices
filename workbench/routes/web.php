<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use UserDevices\Http\Requests\BlockDeviceRequest;

Route::post('/login', function (\Illuminate\Http\Request $request) {
    if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
        $request->session()->regenerate();

        return redirect('/');
    }

    return back()->withErrors(['email' => 'Invalid credentials']);
});

Route::middleware(['auth', 'check.device'])->group(function () {
    Route::get('/dashboard', function () {
        return response()->json(['message' => 'Access granted']);
    });
});

Route::get('/', fn () => response()->json(['ok' => true]));

Route::get('/devices/block/{id}/{hash}', function (BlockDeviceRequest $request) {
    $request->fulfill();

    return redirect('/')->with('message', 'Device blocked successfully.');
})->middleware(['signed', 'throttle:6,1'])->name('user-devices.block');
