<?php

namespace UserDevices\Listeners;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Request;
use UserDevices\DeviceCreator;
use UserDevices\Traits\HasUserDevices;

class SaveUserDevice
{
    /**
     * Handle the event.
     */
    public function handle(Authenticated $event): void
    {
        /** @var mixed $user */
        $user = $event->user;

        if (! in_array(HasUserDevices::class, class_uses_recursive($user))) {
            return;
        }

        $ignore = Context::get('user_devices.ignore_notification', false);

        $device = $user->userDevices()->firstOrNew([
            'ip_address' => Request::ip(),
            'user_agent' => with(Request::userAgent(), DeviceCreator::$userAgent),
        ]);

        tap($device->exists, function ($exists) use ($user, $device, $ignore) {
            $device->fill([
                'last_activity' => Carbon::now()->timestamp,
            ])->save();

            if (! $exists && ! $ignore) {
                $user->sendNewLoginDeviceNotification($device);
            }
        });
    }
}
