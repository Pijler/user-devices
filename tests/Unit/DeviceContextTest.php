<?php

use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Facade;
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

test('it should resolve the context only once per request', function () {
    $calls = 0;

    DeviceCreator::resolveLocationUsing(function () use (&$calls) {
        $calls++;

        return 'Paris, France';
    });

    $request = HttpRequest::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);

    Facade::clearResolvedInstance('request');
    $this->instance('request', $request);

    expect(DeviceContext::fromRequest())->toBe(DeviceContext::fromRequest());
    expect($calls)->toBe(1);

    DeviceCreator::$resolveLocation = null;
});

test('it should resolve a new context for a new request', function () {
    $first = HttpRequest::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.1']);

    Facade::clearResolvedInstance('request');
    $this->instance('request', $first);

    $context = DeviceContext::fromRequest();

    $second = HttpRequest::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.2']);

    Facade::clearResolvedInstance('request');
    $this->instance('request', $second);

    expect(DeviceContext::fromRequest())->not->toBe($context);
    expect(DeviceContext::fromRequest()->ipAddress)->toBe('10.0.0.2');
});
