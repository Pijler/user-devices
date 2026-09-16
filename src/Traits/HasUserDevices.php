<?php

namespace Pijler\UserDevices\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Pijler\UserDevices\DeviceCreator;
use Pijler\UserDevices\DTO\DeviceContext;
use Pijler\UserDevices\Models\UserDevice;
use Pijler\UserDevices\Notifications\AttemptingLoginNotification;
use Pijler\UserDevices\Notifications\AuthenticatedLoginNotification;
use Pijler\UserDevices\Notifications\FailedLoginNotification;

trait HasUserDevices
{
    /**
     * Current request devices already resolved for this user, keyed by IP + user agent.
     *
     * @var array<string, UserDevice>
     */
    private array $currentUserDevices = [];

    /**
     * Get the user devices that belong to the model.
     */
    public function userDevices(): HasMany
    {
        $modelClass = DeviceCreator::$userDeviceModel;

        return $this->hasMany($modelClass);
    }

    /**
     * Send the failed login notification.
     */
    public function sendFailedLoginNotification(UserDevice $device): void
    {
        $this->notify(new FailedLoginNotification($device));
    }

    /**
     * Send the attempting login notification.
     */
    public function sendAttemptingLoginNotification(UserDevice $device): void
    {
        $this->notify(new AttemptingLoginNotification($device));
    }

    /**
     * Send the authenticated login notification.
     */
    public function sendAuthenticatedLoginNotification(UserDevice $device): void
    {
        $this->notify(new AuthenticatedLoginNotification($device));
    }

    /**
     * Check if the current request's device (IP + user agent) is blocked for this user.
     * Use in login controller or FormRequest to prevent blocked devices from attempting login.
     */
    public function isCurrentDeviceBlocked(): bool
    {
        $context = DeviceContext::fromRequest();

        if (blank($context->ipAddress) && blank($context->userAgent)) {
            return false;
        }

        $device = $this->currentDevice();

        return $device->exists && $device->blocked;
    }

    /**
     * Resolve the current request's device (IP + user agent) for this user.
     *
     * The result is memoized on the user instance so the blocked-device check
     * and last-activity update share a single SELECT per request.
     */
    public function currentDevice(): UserDevice
    {
        $context = DeviceContext::fromRequest();

        $cacheKey = "{$context->ipAddress}|{$context->userAgent}";

        if (data_has($this->currentUserDevices, $cacheKey)) {
            return data_get($this->currentUserDevices, $cacheKey);
        }

        $device = $this->userDevices()->firstOrNew([
            'ip_address' => $context->ipAddress,
            'user_agent' => $context->userAgent,
        ]);

        return tap($device, function ($device) use ($cacheKey) {
            data_set($this->currentUserDevices, $cacheKey, $device);
        });
    }
}
