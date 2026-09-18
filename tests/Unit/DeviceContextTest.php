<?php

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Pijler\UserDevices\DeviceCreator;
use Pijler\UserDevices\DTO\DeviceContext;

test('it should create context with named parameters', function () {
    $context = new DeviceContext(
        ipAddress: '1.2.3.4',
        userAgent: 'Mozilla/5.0',
        sessionId: 'session-123',
        location: 'Paris, France',
    );

    expect($context->ipAddress)->toBe('1.2.3.4');
    expect($context->userAgent)->toBe('Mozilla/5.0');
    expect($context->sessionId)->toBe('session-123');
    expect($context->location)->toBe('Paris, France');
});

test('it should resolve location only once when creating context from the request', function () {
    $calls = 0;

    DeviceCreator::resolveLocationUsing(function () use (&$calls) {
        $calls++;

        return 'Paris, France';
    });

    $this->session([]);
    Request::instance()->setLaravelSession(Session::driver());

    DeviceContext::fromRequest();
    DeviceContext::fromRequest();

    expect($calls)->toBe(1);

    DeviceCreator::$resolveLocation = null;
});
