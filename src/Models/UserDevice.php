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
     * Scope a query to check if the device is blocked.
     */
    #[Scope]
    protected function isBlocked(Builder $query, mixed $ipAddress = null, mixed $userAgent = null): bool
    {
        return $query->when(filled($ipAddress), function (Builder $query) use ($ipAddress) {
            $query->where('ip_address', $ipAddress);
        })->when(filled($userAgent), function (Builder $query) use ($userAgent) {
            $query->where('user_agent', $userAgent);
        })->where('blocked', true)->exists();
    }

    /**
     * Scope a query to the devices matching the given context.
     *
     * With the cookie strategy, legacy devices saved without a token are matched
     * by IP + user agent so they can be adopted instead of reported as new.
     */
    #[Scope]
    protected function matchingContext(Builder $query, DeviceContext $context): void
    {
        if ($context->identifyBy === 'cookie') {
            $query->where(function (Builder $query) use ($context) {
                $query->where('device_token', $context->deviceToken)->orWhere(function (Builder $query) use ($context) {
                    $query->whereNull('device_token')
                        ->where('ip_address', $context->ipAddress)
                        ->where('user_agent', $context->userAgent);
                });
            })->orderByRaw('device_token is null');
        } else {
            foreach ($context->identifyBy as $attribute) {
                $query->where($attribute, $context->attributes()[$attribute]);
            }
        }

        $query->latest('last_activity');
    }
}
