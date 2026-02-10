<?php

namespace UserDevices\Listeners;

use Illuminate\Auth\Events\Failed;
use UserDevices\Traits\HandlesAuthEvents;

class FailedLoginListener
{
    use HandlesAuthEvents;

    /**
     * Handle the event.
     */
    public function handle(Failed $event): void
    {
        if ($this->shouldSkipListener()) {
            return;
        }

        $user = $this->resolveUser($event->user, $event->credentials);

        if (blank($user)) {
            return;
        }

        $this->createOrUpdateDeviceAndNotifyIfNew($user, function ($user, $device) {
            $user->sendFailedLoginNotification($device);
        });
    }
}
