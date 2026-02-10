<?php

namespace UserDevices\Listeners;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Support\Facades\Config;
use UserDevices\Traits\HandlesAuthEvents;

class AuthenticatedLoginListener
{
    use HandlesAuthEvents;

    /**
     * Handle the event.
     */
    public function handle(Authenticated $event): void
    {
        if (! Config::get('user-devices.events.authenticated', true)) {
            return;
        }

        if ($this->shouldSkipListener()) {
            return;
        }

        $user = $this->resolveUser($event->user);

        if (blank($user)) {
            return;
        }

        $this->createOrUpdateDeviceAndNotifyIfNew($user, function ($user, $device) {
            $user->sendAuthenticatedLoginNotification($device);
        });
    }
}
