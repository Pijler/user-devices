<?php

namespace UserDevices\Notifications;

use Closure;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;
use UserDevices\Models\UserDevice;

class AttemptingLoginNotification extends Notification
{
    /**
     * The callback that should be used to build the mail message.
     */
    public static ?Closure $toMailCallback = null;

    /**
     * The callback that should be used to create the block device URL.
     */
    public static ?Closure $createBlockUrlCallback = null;

    /**
     * Create a notification instance.
     */
    public function __construct(
        public UserDevice $device
    ) {}

    /**
     * Get the notification's channels.
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $this->device);
        }

        return $this->buildMailMessage();
    }

    /**
     * Get the attempting login notification mail message.
     */
    protected function buildMailMessage(): MailMessage
    {
        $blockUrl = $this->blockDeviceUrl();
        $deviceInfo = $this->formatDeviceInfo();

        return (new MailMessage)
            ->subject(Lang::get('Login Attempt to Your Account'))
            ->line(Lang::get('Someone attempted to log in to your account.'))
            ->line(Lang::get('Device details: :details', ['details' => $deviceInfo]))
            ->action(Lang::get('Block this device'), $blockUrl)
            ->line(Lang::get('If this was you, you can safely ignore this email. If you did not attempt this login, we recommend changing your password immediately.'));
    }

    /**
     * Get the block device URL for the given device.
     */
    protected function blockDeviceUrl(): string
    {
        if (static::$createBlockUrlCallback) {
            return call_user_func(static::$createBlockUrlCallback, $this->device);
        }

        $expire = Config::get('auth.verification.expire', 60);

        return URL::temporarySignedRoute(
            name: 'user-devices.block',
            expiration: Carbon::now()->addMinutes($expire),
            parameters: [
                'id' => $this->device->getKey(),
                'hash' => sha1($this->device->getKey()),
            ],
        );
    }

    /**
     * Format the device information for display.
     */
    protected function formatDeviceInfo(): string
    {
        $parts = [];

        if (filled($this->device->ip_address)) {
            $parts[] = Lang::get('IP Address: :ip', ['ip' => $this->device->ip_address]);
        }

        if (filled($this->device->user_agent)) {
            $parts[] = Lang::get('Device: :device', ['device' => $this->device->user_agent]);
        }

        if (filled($this->device->location)) {
            $parts[] = Lang::get('Location: :location', ['location' => $this->device->location]);
        }

        return filled($parts) ? implode(' | ', $parts) : Lang::get('Unknown device');
    }

    /**
     * Set a callback that should be used when building the notification mail message.
     */
    public static function toMailUsing(Closure $callback): void
    {
        static::$toMailCallback = $callback;
    }

    /**
     * Set a callback that should be used when creating the block device URL.
     */
    public static function createBlockUrlUsing(Closure $callback): void
    {
        static::$createBlockUrlCallback = $callback;
    }
}
