# NotifyTemplates facade

`Fomvasss\NotifyTemplates\Facades\NotifyTemplates` proxies the `NotifyTemplatesManager` singleton. The alias `NotifyTemplates` is registered by package auto-discovery. The manager can also be injected directly.

Every method with `?string $tenantId` replaces `null` with `config('notify-templates.tenant_id')` (see [Multi-tenancy](../usage/multi-tenancy.md)).

## Type registry

| Method | Returns | Description |
|---|---|---|
| `registerType(array $type)` | `void` | Register or replace a type by its `key`; throws `InvalidArgumentException` when `key` is empty |
| `registerTypes(array $types)` | `void` | `registerType()` for each item |
| `discoverIn(string $path)` | `void` | Register every `BaseNotify` subclass found recursively in a directory; a missing directory is ignored |
| `getTypes(?string $group = null)` | `array<string, array>` | All types keyed by `key`, or only those of a group |
| `getType(string $key)` | `?array` | The type definition as registered, custom keys included |
| `getTypeChannels(string $notifyKey)` | `array` | The type's `channels`, or `config('notify-templates.channels')` when empty |
| `getTypeSlots(string $notifyKey)` | `array` | Distinct template slots of the type's channels, in channel order — e.g. `['mail', 'messenger']` |
| `getChannels()` | `array<string, array>` | Every slug of `config('notify-templates.channels')` with its metadata (`key`, `label`, `slot` and the extra keys of [`channel_options`](../configuration.md#channel_options)) |
| `getChannel(string $channel)` | `array` | Metadata of one slug; the defaults for a slug without `channel_options` |
| `getSlots()` | `array<string, array>` | Distinct slots of the configured channels with their metadata (`key`, `label`, `subject` and the extra keys of [`slot_options`](../configuration.md#slot_options)) |
| `getSlot(string $slot)` | `array` | Metadata of one slot; the defaults for a slot without `slot_options` |
| `isUserConfigurable(string $notifyKey)` | `bool` | `false` only when the type has `'user_configurable' => false`; `true` for unknown keys |

## Templates and buttons

| Method | Returns | Description |
|---|---|---|
| `resolveTemplate(string $notifyKey, string $channel, ?string $roleKey = null, ?string $tenantId = null)` | `?NotifyTemplate` | Most specific template row for a slot, see [fallback chain](../usage/templates.md#fallback-chain) |
| `resolveMessengerTemplate(string $notifyKey, string $slot = 'messenger', ?string $roleKey = null, ?string $tenantId = null)` | `?NotifyTemplate` | The `$slot` row when it has a body, otherwise the `messenger` row — see [per-channel text](../usage/templates.md#per-channel-text) |
| `resolveButtons(string $notifyKey, ?string $roleKey = null, ?string $tenantId = null, string $channel = 'messenger', ?array $type = null)` | `array` | Raw buttons: template `options.buttons` (the `$channel` row, then the `messenger` row) → `buttons_by_role[role]` → `buttons`; tokens not substituted; entries without text or url removed. `$type` defaults to the registered definition |
| `resolveButtonsColumns(...)` | `int` | Same arguments; template `options.buttons_columns` (same row order) → `buttons_columns` → `1`; at least `1` |
| `localizeButtonText(mixed $text, ?string $locale = null)` | `string` | A string trimmed; a locale map → locale (current by default) → `app.fallback_locale` → first non-empty entry |

## Channels, delay, preferences

| Method | Returns | Description |
|---|---|---|
| `resolveChannels(string $notifyKey, string $roleKey, ?string $tenantId = null, array $userChannels = [])` | `array` | Subscription channels (`default_channels` when empty); `[]` when there is no subscription or it is inactive. A non-empty `$userChannels` is intersected with them |
| `resolveDelay(string $notifyKey, string $roleKey, ?string $tenantId = null)` | `int` | Seconds: subscription `options.delay` × 60; `0` without a subscription |
| `isNotifyEnabled(string $notifyKey, mixed $notifiable)` | `bool` | `false` only when a `notify_user_settings` row disables the type; always `true` for non-configurable types and non-model notifiables |
| `resolveNotifyUserChannels(string $notifyKey, mixed $notifiable)` | `?array` | The per-type channel override; `null` when none, for non-configurable types and non-model notifiables |
| `resolveTenantId(?string $tenantId)` | `?string` | The argument, or the configured tenant (callable evaluated) |

`resolveChannels()` works with slugs; it doesn't call `mapChannel()` and doesn't apply the per-user steps of `via()`.

## Delivery log

| Method | Returns | Description |
|---|---|---|
| `updateDelivery(string $channel, string $externalId, string $status, array $payload = [])` | `bool` | Apply a provider report to the newest log row with this channel and external id. `false` when nothing matched or the status would move backwards; `InvalidArgumentException` for `pending` or an unknown status. See [Delivery log](../usage/delivery-log.md#delivery-status) |
