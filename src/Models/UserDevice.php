<?php

namespace Pijler\UserDevices\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pijler\UserDevices\DeviceCreator;
use Pijler\UserDevices\DTO\DeviceContext;

class UserDevice extends Model
{
    use HasUuids;

    /**
     * The attributes that aren't mass assignable.
     */
    protected $guarded = [];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'blocked' => 'boolean',
            'last_activity' => 'timestamp',
        ];
    }

    /**
     * Get the user that the device belongs to.
     */
    public function user(): BelongsTo
    {
        $model = DeviceCreator::$userModel;

        return $this->belongsTo($model, (new $model)->getForeignKey());
    }

    /**
     * Block the device.
     */
    public function block(): void
    {
        $this->update(['blocked' => true]);
    }

    /**
     * Unblock the device.
     */
    public function unblock(): void
    {
        $this->update(['blocked' => false]);
    }

    /**
     * Mark the device as blocked.
     */
    public static function markAsBlocked(mixed $id): void
    {
        self::where('id', $id)->update(['blocked' => true]);
    }

    /**
     * Mark the device as unblocked.
     */
    public static function markAsUnblocked(mixed $id): void
    {
        self::where('id', $id)->update(['blocked' => false]);
    }

    /**
     * Scope a query to check if the device matching the current request is blocked.
     */
    #[Scope]
    protected function isBlocked(Builder $query): bool
    {
        return (bool) $query->matchingContext()->value('blocked');
    }

    /**
     * Scope a query to the devices matching the current request's context.
     *
     * The cookie strategy matches by device token. Without a stored token match:
     * - a cookie sent by the client only adopts legacy devices (no token) by IP + user agent;
     * - a token generated in this request (client without cookie) matches by IP + user agent,
     *   so clients that never keep the cookie don't create a new device on every request;
     * - a request without a session can't keep the cookie, so it matches untokened devices by user agent.
     *
     * A device matched by token comes first, as it's the most reliable identification.
     * Among the remaining matches, blocked devices come first so they are never hidden.
     */
    #[Scope]
    protected function matchingContext(Builder $query): void
    {
        $context = DeviceContext::fromRequest();

        $usesCookie = $context->identifyBy === 'cookie';

        $query->when(! $usesCookie, function (Builder $query) use ($context) {
            foreach ($context->identifyBy as $attribute) {
                $query->where($attribute, data_get($context->attributes(), $attribute));
            }
        })->when($usesCookie && blank($context->deviceToken), function (Builder $query) use ($context) {
            $query
                ->whereNull('device_token')
                ->where('user_agent', $context->userAgent);
        })->when($usesCookie && filled($context->deviceToken), function (Builder $query) use ($context) {
            $query->where(function (Builder $query) use ($context) {
                $query->where('device_token', $context->deviceToken)->orWhere(function (Builder $query) use ($context) {
                    $query
                        ->where('ip_address', $context->ipAddress)
                        ->where('user_agent', $context->userAgent)
                        ->when(! $context->newDeviceToken, fn (Builder $query) => $query->whereNull('device_token'));
                });
            })->orderByRaw('case when device_token = ? then 0 else 1 end', [$context->deviceToken]);
        })->orderByDesc('blocked')->latest('last_activity');
    }
}
