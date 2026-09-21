<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotifyLog extends Model
{
    use MassPrunable;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';

    // Delivery reports arrive out of order (e.g. Meta may send `delivered` after `read`), so a
    // report may only move the status forward. `failed` ranks with `delivered`: a provider can
    // fail a message it had accepted, but not one it already delivered.
    private const RANKS = [
        self::STATUS_PENDING => 0,
        self::STATUS_SENT => 1,
        self::STATUS_DELIVERED => 2,
        self::STATUS_FAILED => 2,
        self::STATUS_READ => 3,
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'status_updated_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('notify-templates.tables.notify_logs', 'notify_logs');
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function statuses(): array
    {
        return array_keys(self::RANKS);
    }

    public function canMoveTo(string $status): bool
    {
        return self::RANKS[$status] > (self::RANKS[$this->status] ?? -1);
    }

    public function prunable(): Builder
    {
        $days = (int) config('notify-templates.log.retention_days', 90);

        return static::query()->where('created_at', '<', now()->subDays($days));
    }
}
