<?php

namespace UserDevices\Traits;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Context;
use UserDevices\DeviceCreator;
use UserDevices\DTO\DeviceContext;
use UserDevices\Models\UserDevice;

trait HandlesAuthEvents
{
    /**
     * Check if the listener should be skipped.
     */
    protected function shouldSkipListener(): bool
    {
        return Context::get('user_devices.ignore_listener', false);
    }

    /**
     * Determine whether to send the notification.
     */
    protected function shouldSendNotification(mixed $user, ?UserDevice $device = null): bool
    {
        if (filled($device) && is_callable(DeviceCreator::$shouldSendNotification)) {
            return (DeviceCreator::$shouldSendNotification)($user, $device);
        }

        return Context::get('user_devices.ignore_notification', false) === false;
    }

    /**
     * Resolve and validate the user for notification.
     */
    protected function resolveUser(mixed $eventUser = null, ?array $credentials = null): mixed
    {
        $user = $eventUser ?? (filled($credentials) ? $this->resolveUserFromCredentials($credentials) : null);

        if (blank($user) || in_array(HasUserDevices::class, class_uses_recursive($user)) === false) {
            return null;
        }

        return $user;
    }

    /**
     * Resolve the user from credentials (e.g. email).
     */
    protected function resolveUserFromCredentials(array $credentials): mixed
    {
        $userModel = DeviceCreator::$userModel;

        $key = Config::get('user-devices.credential_key', 'email');

        $value = data_get($credentials, $key);

        if (blank($value)) {
            return null;
        }

        return resolve($userModel)::where($key, $value)->first();
    }

    /**
     * Create or update the user device and optionally send notification if it's new.
     */
    protected function createOrUpdateDeviceAndNotifyIfNew(mixed $user, callable $notify): void
    {
        $context = DeviceContext::fromRequest();

        $device = $user->userDevices()->firstOrNew([
            'ip_address' => $context->ipAddress,
            'user_agent' => $context->userAgent,
        ]);

        tap($device->exists, function ($exists) use ($user, $device, $context, $notify) {
            $device->fill([
                'location' => $context->location,
                'session_id' => $context->sessionId,
                'last_activity' => Carbon::now()->timestamp,
            ])->save();

            if (! $exists && $this->shouldSendNotification($user, $device)) {
                $notify($user, $device);
            }
        });
    }
}
