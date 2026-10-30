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
     * Check if the current request's device is blocked for this user.
     * Use in login controller or FormRequest to prevent blocked devices from attempting login.
     */
    public function isCurrentDeviceBlocked(): bool
    {
        $context = DeviceContext::fromRequest();

        if (blank($context->deviceToken) && blank(array_filter($context->attributes()))) {
            return false;
        }

        $device = $this->currentDevice();

        return $device->exists && $device->blocked;
    }

    /**
     * Resolve the current request's device for this user.
     *
     * The device is matched according to the "identify_by" config option, and
     * its location, IP address and user agent are refreshed with the current values.
     *
     * The result is memoized on the request context so the blocked-device check
     * and last-activity update share a single SELECT per request.
     */
    public function currentDevice(): UserDevice
    {
        $context = DeviceContext::fromRequest();

        return $context->rememberDevice($this, fn () => $this->resolveCurrentDevice($context));
    }

    /**
     * Find or make the device matching the given context.
     */
    private function resolveCurrentDevice(DeviceContext $context): UserDevice
    {
        $device = $this->userDevices()->matchingContext()->first() ?? $this->userDevices()->make();

        $device->fill($context->attributes());

        if (filled($context->deviceToken)) {
            $device->device_token = $context->deviceToken;
        }

        return $device;
    }
}
