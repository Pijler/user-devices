<?php

namespace Pijler\UserDevices\DTO;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use InvalidArgumentException;
use Pijler\UserDevices\DeviceCreator;
use Pijler\UserDevices\Services\DeviceTokenResolver;
use Pijler\UserDevices\Services\LocationResolver;

readonly class DeviceContext
{
    /**
     * The device attributes that can be used to identify a device.
     */
    public const IDENTIFIABLE_ATTRIBUTES = [
        'location',
        'ip_address',
        'user_agent',
        ];

    /**
     * Create a new DTO instance.
     */
    public function __construct(
        public ?string $location = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $sessionId = null,
        public ?string $deviceToken = null,
        public string|array $identifyBy = ['ip_address', 'user_agent'],
    ) {}

    /**
     * Get the device attributes described by this context.
     */
    public function attributes(): array
    {
        return [
            'location' => $this->location,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ];
    }

    /**
     * Create from the current request.
     */
    public static function fromRequest(): self
    {
        $ipAddress = Request::ip();

        $location = LocationResolver::resolve($ipAddress ?? '');

        $sessionId = Request::hasSession() ? Session::getId() : null;

        $userAgent = with(Request::userAgent(), DeviceCreator::$userAgent);

        $identifyBy = static::resolveIdentifyBy();

        $deviceToken = $identifyBy === 'cookie' ? DeviceTokenResolver::resolve() : null;

        return new self($location, $ipAddress, $userAgent, $sessionId, $deviceToken, $identifyBy);
    }

    /**
     * Resolve the device identification strategy for the current request.
     *
     * Requests without a session cannot keep the device cookie,
     * so the cookie strategy falls back to the user agent.
     */
    private static function resolveIdentifyBy(): string|array
    {
        $identifyBy = Config::get('user-devices.identify_by', 'cookie');

        if ($identifyBy === 'cookie') {
            return Request::hasSession() ? 'cookie' : ['user_agent'];
        }

        $invalid = is_array($identifyBy) ? array_diff($identifyBy, self::IDENTIFIABLE_ATTRIBUTES) : [];

        if (! is_array($identifyBy) || blank($identifyBy) || filled($invalid)) {
            throw new InvalidArgumentException(sprintf(
                'The [user-devices.identify_by] option must be "cookie" or a list of: %s.',
                implode(', ', self::IDENTIFIABLE_ATTRIBUTES),
            ));
        }

        return array_values($identifyBy);
    }
}
