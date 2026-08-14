<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotifyUserSetting extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_enabled' => 'boolean',
        'channels' => 'array',
    ];

    public function getTable(): string
    {
        return config('notify-templates.tables.notify_user_settings', 'notify_user_settings');
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    private static function forNotifiable(mixed $notifiable, string $notifyKey): ?static
    {
        if (!$notifiable instanceof Model) {
            return null;
        }

        return static::query()
            ->where('notifiable_type', $notifiable->getMorphClass())
            ->where('notifiable_id', $notifiable->getKey())
            ->where('notify_key', $notifyKey)
            ->first();
    }

    /**
     * Absence of a row = enabled. A row only ever records an explicit opt-out/in by the
     * notifiable itself (e.g. a user toggling a notification type off in their profile).
     */
    public static function isEnabledFor(mixed $notifiable, string $notifyKey): bool
    {
        return static::forNotifiable($notifiable, $notifyKey)?->is_enabled ?? true;
    }

    /**
     * Per-type channel override for this notifiable — e.g. this one type only via 'telegram',
     * regardless of the notifiable's global channel preference. Null = no override recorded.
     */
    public static function channelsFor(mixed $notifiable, string $notifyKey): ?array
    {
        return static::forNotifiable($notifiable, $notifyKey)?->channels;
    }
}
