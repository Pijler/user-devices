<?php

namespace UserDevices\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Request;
use UserDevices\DeviceCreator;
use UserDevices\Models\UserDevice;
use UserDevices\Traits\HasUserDevices;

class SaveUserDevice
{
    /**
     * Handle the event. Creates or updates the device on login and sends the new device notification when applicable.
     */
    public function handle(Login $event): void
    {
        /** @var mixed $user */
        $user = $event->user;

        if (Context::get('user_devices.ignore_listener', false)) {
            return;
        }

        if (! in_array(HasUserDevices::class, class_uses_recursive($user))) {
            return;
        }

        $device = $user->userDevices()->firstOrCreate([
            'ip_address' => Request::ip(),
            'user_agent' => with(Request::userAgent(), DeviceCreator::$userAgent),
        ], ['last_activity' => Carbon::now()->timestamp]);

        $shouldSend = $this->shouldSendNotification($user, $device);

        if ($shouldSend && $device->wasRecentlyCreated) {
            $user->sendNewLoginDeviceNotification($device);
        }
    }

    /**
     * Determine whether to send the new login device notification.
     */
    private function shouldSendNotification(mixed $user, UserDevice $device): bool
    {
        if (is_callable(DeviceCreator::$shouldSendNotification)) {
            return (DeviceCreator::$shouldSendNotification)($user, $device);
        }

        return ! Context::get('user_devices.ignore_notification', false);
    }
}
