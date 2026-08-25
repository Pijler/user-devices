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
     * Check if the current request's device (IP + user agent) is blocked for this user.
     * Use in login controller or FormRequest to prevent blocked devices from attempting login.
     */
    public function isCurrentDeviceBlocked(): bool
    {
        $context = DeviceContext::fromRequest();

        if (blank($context->ipAddress) && blank($context->userAgent)) {
            return false;
        }

        return $this->userDevices()->isBlocked($context->ipAddress, $context->userAgent);
    }
}
