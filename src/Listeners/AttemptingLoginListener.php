<?php

namespace UserDevices\Listeners;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Support\Facades\Config;
use UserDevices\Traits\HandlesAuthEvents;

class AttemptingLoginListener
{
    use HandlesAuthEvents;

    /**
     * Handle the event.
     */
    public function handle(Attempting $event): void
    {
        if (! Config::get('user-devices.events.attempting', false)) {
            return;
        }

        if ($this->shouldSkipListener()) {
            return;
        }

        $user = $this->resolveUser(null, $event->credentials);

        if (blank($user)) {
            return;
        }

        $this->createOrUpdateDeviceAndNotifyIfNew($user, function ($user, $device) {
            $user->sendAttemptingLoginNotification($device);
        });
    }
}
