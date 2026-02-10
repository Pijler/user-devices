<?php

use UserDevices\DTO\DeviceContext;

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
