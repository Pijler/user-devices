<?php

namespace UserDevices\Notifications;

use Closure;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;
use UserDevices\Models\UserDevice;

class FailedLoginNotification extends Notification
{
    /**
     * The callback that should be used to build the mail message.
     */
    public static ?Closure $toMailCallback = null;

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
     * Get the failed login notification mail message.
     */
    protected function buildMailMessage(): MailMessage
    {
        $deviceInfo = $this->formatDeviceInfo();

        return (new MailMessage)
            ->subject(Lang::get('Failed Login Attempt to Your Account'))
            ->line(Lang::get('A failed login attempt was detected for your account.'))
            ->line(Lang::get('Device details: :details', ['details' => $deviceInfo]))
            ->line(Lang::get('If this was you, you may have entered the wrong password. If you did not attempt this login, we recommend changing your password immediately.'));
    }

    /**
     * Format the device information for display.
     */
    protected function formatDeviceInfo(): string
    {
        $parts = [];

        if ($this->device->ip_address) {
            $parts[] = Lang::get('IP Address: :ip', ['ip' => $this->device->ip_address]);
        }

        if ($this->device->user_agent) {
            $parts[] = Lang::get('Device: :device', ['device' => $this->device->user_agent]);
        }

        if ($this->device->location) {
            $parts[] = Lang::get('Location: :location', ['location' => $this->device->location]);
        }

        return ! empty($parts) ? implode(' | ', $parts) : Lang::get('Unknown device');
    }

    /**
     * Set a callback that should be used when building the notification mail message.
     */
    public static function toMailUsing(Closure $callback): void
    {
        static::$toMailCallback = $callback;
    }
}
