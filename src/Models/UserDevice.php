<?php

namespace UserDevices\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use UserDevices\DeviceCreator;

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
    protected function isBlocked(Builder $query, mixed $userAgent): bool
    {
        return $query->where('user_agent', $userAgent)->where('blocked', true)->exists();
    }
}
