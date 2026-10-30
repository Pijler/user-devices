<?php

namespace Pijler\UserDevices\DTO;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Pijler\UserDevices\DeviceCreator;
use Pijler\UserDevices\Exceptions\InvalidIdentifyByException;
use Pijler\UserDevices\Exceptions\MissingLocationResolverException;
use Pijler\UserDevices\Models\UserDevice;
use Pijler\UserDevices\Services\DeviceTokenResolver;
use Pijler\UserDevices\Services\LocationResolver;
use WeakMap;

class DeviceContext
{
    /**
     * Contexts already resolved, keyed by request instance.
     *
     * Entries are dropped together with their request, so long-running
     * processes (Octane, queue workers, tests) never share a context.
     */
    private static ?WeakMap $resolved = null;

    /**
     * The device attributes that can be used to identify a device.
     */
    public const IDENTIFIABLE_ATTRIBUTES = [
        'location',
        'ip_address',
        'user_agent',
    ];

    /**
     * Devices already resolved for this context, keyed by user.
     */
    private array $devices = [];

    /**
     * Create a new DTO instance.
     */
    public function __construct(
        public readonly ?string $location = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $sessionId = null,
        public readonly ?string $deviceToken = null,
        public readonly bool $newDeviceToken = false,
        public readonly string|array $identifyBy = 'cookie',
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
     * Create from the current request, resolving it only once per request.
     */
    public static function fromRequest(): self
    {
        static::$resolved ??= new WeakMap;

        return static::$resolved[Request::instance()] ??= static::resolveFromRequest();
    }

    /**
     * Remember the device resolved for the given user in this context.
     */
    public function rememberDevice(Model $user, Closure $resolve): UserDevice
    {
        $key = "{$user->getMorphClass()}.{$user->getKey()}";

        return $this->devices[$key] ??= $resolve();
    }

    /**
     * Get the hashed device token for the cookie strategy.
     *
     * Requests without a session cannot keep the device cookie.
     */
    private static function getDeviceToken(string|array $identifyBy): ?string
    {
        if ($identifyBy === 'cookie' && Request::hasSession()) {
            return DeviceTokenResolver::resolve();
        }

        return null;
    }

    /**
     * Get the device attributes from the current request.
     */
    private static function getAttributesFromRequest(): array
    {
        return [
            'ip_address' => Request::ip(),
            'location' => LocationResolver::resolve(Request::ip() ?? ''),
            'session_id' => Request::hasSession() ? Session::getId() : null,
            'user_agent' => with(Request::userAgent(), DeviceCreator::$userAgent),
        ];
    }

    /**
     * Resolve the context from the current request.
     */
    private static function resolveFromRequest(): self
    {
        $identifyBy = static::resolveIdentifyBy();

        $deviceToken = static::getDeviceToken($identifyBy);

        $newDeviceToken = filled($deviceToken) && DeviceTokenResolver::isGenerated();

        [$ipAddress, $location, $sessionId, $userAgent] = array_values(static::getAttributesFromRequest());

        return new self(
            location: $location,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            sessionId: $sessionId,
            identifyBy: $identifyBy,
            deviceToken: $deviceToken,
            newDeviceToken: $newDeviceToken,
        );
    }

    /**
     * Resolve and validate the device identification strategy.
     */
    private static function resolveIdentifyBy(): string|array
    {
        $identifyBy = Config::get('user-devices.identify_by', 'cookie');

        if ($identifyBy === 'cookie') {
            return $identifyBy;
        }

        $attributes = collect(is_array($identifyBy) ? $identifyBy : []);

        $valid = $attributes->isNotEmpty() && $attributes->every(function (mixed $attribute) {
            return in_array($attribute, self::IDENTIFIABLE_ATTRIBUTES, true);
        });

        throw_unless($valid, InvalidIdentifyByException::make());

        throw_if($attributes->contains('location') && blank(DeviceCreator::$resolveLocation), MissingLocationResolverException::make());

        return $attributes->values()->all();
    }
}
