# Tables & models

All models are in `Fomvasss\NotifyTemplates\Models`, use `$guarded = ['id']`, and take their table name from `config('notify-templates.tables.*')`. Each can be replaced through `config('notify-templates.models.*')`.

## notify_templates — NotifyTemplate

| Column | Type | Description |
|---|---|---|
| `id` | bigint | |
| `notify_key` | string(100) | Type key |
| `channel` | string(50), null | Slot: `mail`, `messenger`, custom; `null` = any |
| `role_key` | string(100), null | `null` = any role |
| `tenant_id` | string(100), null | `null` = any tenant |
| `subject` | text, null | |
| `body` | longtext, null | |
| `options` | json, null | Cast to array; `buttons`, `buttons_columns`, your own keys |
| `created_at`, `updated_at` | timestamp | |

Unique index `nt_lookup` on `(notify_key, COALESCE(channel,''), COALESCE(role_key,''), COALESCE(tenant_id,''))`.

| Method | Description |
|---|---|
| `static resolve(string $notifyKey, string $channel, ?string $roleKey = null, ?string $tenantId = null): ?static` | Most specific row — slot > role > tenant. No config tenant fallback; use the facade for that |
| `getOption(string $key, mixed $default = null): mixed` | `data_get()` on `options` |

## notify_role_subscriptions — NotifyRoleSubscription

| Column | Type | Description |
|---|---|---|
| `id` | bigint | |
| `role_key` | string(100) | |
| `notify_key` | string(100) | |
| `tenant_id` | string(100), null | `null` = all tenants |
| `is_active` | boolean, default `true` | |
| `personal_only` | boolean, default `false` | For your resolver only |
| `channels` | json, null | Channel slugs; empty = `default_channels` |
| `options` | json, null | `delay` (minutes) and other `settings` values |
| `created_at`, `updated_at` | timestamp | |

Unique index `nrs_unique` on `(role_key, notify_key, COALESCE(tenant_id,''))`.

| Method | Description |
|---|---|
| `scopeActive()` | `is_active = true` |
| `scopeForNotify(string $notifyKey)` | `notify_key = ?` |
| `scopeForTenant(?string $tenantId)` | With a tenant: the tenant's and global rows; `null`: global rows |
| `static resolve(string $roleKey, string $notifyKey, ?string $tenantId = null): ?static` | Tenant row if it exists, otherwise global; doesn't filter by `is_active` |
| `getOption(string $key, mixed $default = null): mixed` | `data_get()` on `options` |
| `getDelaySeconds(): int` | `options.delay` × 60 |

## notify_user_settings — NotifyUserSetting

| Column | Type | Description |
|---|---|---|
| `id` | bigint | |
| `notifiable_type` | string | Morph class |
| `notifiable_id` | string | String, so integer and UUID keys both fit |
| `notify_key` | string(100) | |
| `is_enabled` | boolean, default `true` | |
| `channels` | json, null | `null` = no override |
| `created_at`, `updated_at` | timestamp | |

Unique index `nus_unique` on `(notifiable_type, notifiable_id, notify_key)`.

| Method | Description |
|---|---|
| `notifiable(): MorphTo` | |
| `static isEnabledFor(mixed $notifiable, string $notifyKey): bool` | `true` without a row or for a non-model notifiable. Ignores `user_configurable` — use the facade |
| `static channelsFor(mixed $notifiable, string $notifyKey): ?array` | The override, or `null` |

## notify_logs — NotifyLog

| Column | Type | Description |
|---|---|---|
| `id` | bigint | |
| `notification_id` | string(36) | Laravel's notification id, shared by channels and retries |
| `notify_key` | string(100) | |
| `channel` | string | As returned by `via()`: `mail`, `telegram`, a class name |
| `role_key` | string(100), null | |
| `tenant_id` | string(100), null | |
| `notifiable_type`, `notifiable_id` | string, null | `null` for on-demand recipients |
| `route` | string, null | Email, chat id, phone |
| `subject` | string, null | Sent subject, cut to 255 |
| `body` | longtext, null | Sent body, unless disabled |
| `status` | string(20) | `pending`, `sent`, `delivered`, `read`, `failed` |
| `external_id` | string, null | Provider message id |
| `error` | text, null | Failure message |
| `payload` | json, null | Last provider report or failure data |
| `attempts` | smallint, default `1` | |
| `status_updated_at` | timestamp, null | |
| `created_at`, `updated_at` | timestamp | |

Indexes: `notification_id`, `(channel, external_id)`, `(notifiable_type, notifiable_id)`, `created_at`.

| Member | Description |
|---|---|
| `STATUS_PENDING`, `STATUS_SENT`, `STATUS_DELIVERED`, `STATUS_READ`, `STATUS_FAILED` | Status constants |
| `static statuses(): array` | All statuses in lifecycle order |
| `static statusLabels(): array` | Status → translated label (`notify-templates::log.statuses.*`) |
| `getStatusLabel(): string` | Label of this row's status |
| `canMoveTo(string $status): bool` | Whether a report with this status may replace the current one |
| `notifiable(): MorphTo` | |
| `prunable(): Builder` | Rows older than `log.retention_days` (`MassPrunable`) |

## Table names in migrations

The migrations create the tables with the default names. Setting `tables.*` to other names requires editing the published migrations as well.
