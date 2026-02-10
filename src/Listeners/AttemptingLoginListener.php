<?php

namespace UserDevices\Listeners;

use Illuminate\Auth\Events\Attempting;
use UserDevices\Traits\HandlesAuthEvents;

class AttemptingLoginListener
{
    use HandlesAuthEvents;

    /**
     * Handle the event.
     */
    public function handle(Attempting $event): void
    {
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
