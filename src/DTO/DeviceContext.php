<?php

namespace UserDevices\DTO;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use UserDevices\DeviceCreator;
use UserDevices\Services\LocationResolver;

readonly class DeviceContext
{
    /**
     * Create a new DTO instance.
     */
    public function __construct(
        public ?string $location = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $sessionId = null,
    ) {}

    /**
     * Create from the current request.
     */
    public static function fromRequest(): self
    {
        $ipAddress = Request::ip();

        $location = LocationResolver::resolve($ipAddress ?? '');

        $sessionId = Request::hasSession() ? Session::getId() : null;

        $userAgent = with(Request::userAgent(), DeviceCreator::$userAgent);

        return new self($location, $ipAddress, $userAgent, $sessionId);
    }
}
