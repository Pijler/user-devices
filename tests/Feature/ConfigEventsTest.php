<?php

use Illuminate\Support\Facades\Config;
use Workbench\App\Models\User;

test('it should not save device when authenticated event is disabled', function () {
    Config::set('user-devices.events.authenticated', false);

    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard');

    $user->refresh();

    expect($user->userDevices)->toHaveCount(0);

    Config::set('user-devices.events.authenticated', true);
});
