<?php

namespace UserDevices\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use UserDevices\DeviceCreator;
use UserDevices\Models\UserDevice;
use UserDevices\Notifications\NewLoginDeviceNotification;

trait HasUserDevices
{
    /**
     * Get the personal tokens that belong to model.
     */
    public function userDevices(): HasMany
    {
        $modelClass = DeviceCreator::$userDeviceModel;

        return $this->hasMany($modelClass);
    }

    /**
     * Send the new login device notification.
     */
    public function sendNewLoginDeviceNotification(UserDevice $device): void
    {
        $this->notify(new NewLoginDeviceNotification($device));
    }
}
