<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NotifyTemplate extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $casts = [
        'options' => 'array',
    ];

    public function getTable(): string
    {
        return config('notify-templates.tables.notify_templates', 'notify_templates');
    }

    /**
     * Resolve the best-matching template using fallback chain:
     * (channel + role + tenant) → (channel + role) → (channel + tenant) → (channel)
     * → (null + role + tenant) → (null + role) → (null + tenant) → (null)
     *
     * Specificity: channel match > role match > tenant match.
     */
    public static function resolve(
        string $notifyKey,
        string $channel,
        ?string $roleKey = null,
        ?string $tenantId = null,
    ): ?static {
        return static::query()
            ->where('notify_key', $notifyKey)
            ->where(fn($q) => $q->where('channel', $channel)->orWhereNull('channel'))
            ->where(function ($q) use ($roleKey) {
                $roleKey
                    ? $q->where('role_key', $roleKey)->orWhereNull('role_key')
                    : $q->whereNull('role_key');
            })
            ->where(function ($q) use ($tenantId) {
                $tenantId
                    ? $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id')
                    : $q->whereNull('tenant_id');
            })
            ->orderByRaw('
                (CASE WHEN channel IS NOT NULL THEN 4 ELSE 0 END) +
                (CASE WHEN role_key IS NOT NULL THEN 2 ELSE 0 END) +
                (CASE WHEN tenant_id IS NOT NULL THEN 1 ELSE 0 END) DESC
            ')
            ->first();
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return data_get($this->options, $key, $default);
    }
}
