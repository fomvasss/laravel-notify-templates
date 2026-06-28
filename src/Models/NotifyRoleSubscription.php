<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotifyRoleSubscription extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'personal_only' => 'boolean',
        'channels' => 'array',
        'options' => 'array',
    ];

    public function getTable(): string
    {
        return config('notify-templates.tables.notify_role_subscriptions', 'notify_role_subscriptions');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeForNotify(Builder $query, string $notifyKey): void
    {
        $query->where('notify_key', $notifyKey);
    }

    public function scopeForTenant(Builder $query, ?string $tenantId): void
    {
        if ($tenantId) {
            $query->where(fn($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'));
        } else {
            $query->whereNull('tenant_id');
        }
    }

    /**
     * Resolve the entry for a specific role+notify, preferring tenant-specific over global.
     */
    public static function resolve(
        string $roleKey,
        string $notifyKey,
        ?string $tenantId = null,
    ): ?static {
        return static::query()
            ->where('role_key', $roleKey)
            ->where('notify_key', $notifyKey)
            ->where(function ($q) use ($tenantId) {
                $tenantId
                    ? $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id')
                    : $q->whereNull('tenant_id');
            })
            ->orderByRaw('tenant_id IS NOT NULL DESC')
            ->first();
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return data_get($this->options, $key, $default);
    }

    /**
     * Delay in seconds (options.delay is stored in minutes).
     */
    public function getDelaySeconds(): int
    {
        return (int) ($this->getOption('delay', 0)) * 60;
    }
}
