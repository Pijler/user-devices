<?php

namespace UserDevices\Listeners;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Request;
use UserDevices\DeviceCreator;
use UserDevices\Traits\HasUserDevices;

class UpdateDeviceLastActivity
{
    /**
     * Handle the event. Only updates last_activity for the current device; does not create devices or send notifications.
     */
    public function handle(Authenticated $event): void
    {
        /** @var mixed $user */
        $user = $event->user;

        if (Context::get('user_devices.ignore_listener', false)) {
            return;
        }

        if (! in_array(HasUserDevices::class, class_uses_recursive($user))) {
            return;
        }

        $userAgent = with(Request::userAgent(), DeviceCreator::$userAgent);

        $device = $user->userDevices()
            ->where('ip_address', Request::ip())
            ->where('user_agent', $userAgent)
            ->first();

        if (filled($device)) {
            $device->update(['last_activity' => Carbon::now()->timestamp]);
        }
    }
}
