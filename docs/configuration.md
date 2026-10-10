# Configuration

`config/notify-templates.php`. The file reads no environment variables — every value is set in the file itself.

> [!WARNING]
> `mergeConfigFrom()` merges top-level keys only. Once the config is published, a nested array such as `log`, `tables` or `models` is taken from your file as a whole: keys added to it in a later package version are missing until you copy them in. Compare your file with the package's after every upgrade.

## Keys

| Key | Default | Description |
|---|---|---|
| `tables.notify_templates` | `notify_templates` | Table of the `NotifyTemplate` model |
| `tables.notify_role_subscriptions` | `notify_role_subscriptions` | Table of the `NotifyRoleSubscription` model |
| `tables.notify_user_settings` | `notify_user_settings` | Table of the `NotifyUserSetting` model |
| `tables.notify_logs` | `notify_logs` | Table of the `NotifyLog` model |
| `channels` | `['mail', 'telegram', 'sms', 'database', 'broadcast']` | Channel slugs offered in your admin UI; fallback of `getTypeChannels()` when a type defines no `channels` |
| `channel_options` | `mail`, `sms` labels | Per-channel metadata for the admin UI: `label`, template `slot`, any extra keys |
| `slot_options` | `mail`, `messenger`, `sms` labels | Per-slot metadata for the admin UI: `label`, `subject`, any extra keys |
| `default_channels` | `['mail']` | Channels used when an active subscription has an empty `channels` list, and the guaranteed-delivery fallback for non-configurable types |
| `tenant_id` | `null` | Tenant used when no tenant is passed explicitly: `null`, a string, or a callable returning one |
| `types` | `[]` | Notify types registered from config, in addition to discovered ones |
| `discover` | `[app_path('Notifications')]` | Directories scanned for `BaseNotify` subclasses on every boot; `[]` disables discovery |
| `models.notify_template` | `NotifyTemplate::class` | Model class used to resolve templates |
| `models.notify_role_subscription` | `NotifyRoleSubscription::class` | Model class used to resolve subscriptions |
| `models.notify_user_setting` | `NotifyUserSetting::class` | Model class used for per-notifiable settings |
| `models.notify_log` | `NotifyLog::class` | Model class of the delivery log |
| `log.enabled` | `false` | Write the delivery log |
| `log.retention_days` | `90` | Age after which `model:prune` deletes log rows |
| `log.external_id_resolvers` | `mail`, `telegram` | Channel → `ExternalIdResolverInterface` class |
| `log.content_resolvers` | `mail`, `telegram` | Channel → `ContentResolverInterface` class |
| `log.store_body` | `true` | Store the sent body in the log; `false` keeps the subject only |

## tables

Only the models read these names. The published migrations create tables with the default names hard-coded — to use other names, edit the published migration files as well.

## channels

The package does not validate anything against this list. It is what `NotifyTemplates::getTypeChannels($key)` returns for a type without its own `channels`, meant for the channel checkboxes of a subscription form. It has no effect on which channels a notification is actually sent through — see [Channel resolution](usage/channels.md).

## channel_options

Metadata of the slugs from `channels`, read by `getChannels()` / `getChannel()` for an admin UI — a matrix header, an icon next to a toggle, a preview tab per template slot. Nothing here affects sending.

```php
'channel_options' => [
    'mail' => ['label' => 'Email', 'icon' => 'fas fa-envelope'],
    'telegram' => ['icon' => 'fab fa-telegram'],
    'sms' => ['label' => 'SMS', 'slot' => 'sms'],
],
```

| Key | Default | Meaning |
|---|---|---|
| `label` | slug with an upper-case first letter | Display name |
| `slot` | `mail` for `mail`, `messenger` for the rest | Template slot the channel renders. A slot of its own lets the channel override the shared messenger text — pass it to `getMessengerBody()`, see [per-channel text](usage/templates.md#per-channel-text) |
| anything else | — | Returned as is |

A channel without an entry gets the defaults, so a new slug needs no entry at all.

## slot_options

Metadata of template slots — the `slot` of a channel — read by `getSlots()` / `getSlot()`. A slot is the template an admin edits; several channels can share one (`telegram` and `whatsapp` both render `messenger`). Keep what the edit form and the preview need here instead of `if ($slot === 'mail')` in the views:

```php
'slot_options' => [
    'mail' => ['label' => 'Email', 'html' => true, 'rows' => 30],
    'messenger' => ['label' => 'Messenger', 'rows' => 6],
    'sms' => ['label' => 'SMS', 'max_length' => 160],
],
```

| Key | Default | Meaning |
|---|---|---|
| `label` | slot with an upper-case first letter | Display name — a tab or card title |
| `subject` | `true` for `mail`, `false` for the rest | The slot has a subject line |
| anything else | — | Returned as is |

Nothing here affects sending.

## default_channels

Used in two places:

1. `resolveChannels()` — an **active** subscription whose `channels` is empty or `null` resolves to `default_channels`.
2. `BaseNotify::via()` — when nothing survived resolution, a type with `'user_configurable' => false` falls back to `default_channels`. Regular types send nothing.

In both cases the values are channel slugs and go through `mapChannel()`: `mail` is skipped for a notifiable without a mail route, and your own channels need a `mapChannel()` entry ([Custom channels](usage/custom-channels.md)).

## tenant_id

```php
'tenant_id' => null,                                  // single tenant
'tenant_id' => 'shop-ua',                             // fixed tenant
'tenant_id' => [\App\Support\Tenant::class, 'id'],    // callable, evaluated on every resolution
```

The value is read by `resolveTemplate()`, `resolveChannels()`, `resolveDelay()`, `resolveButtons()` and the delivery log whenever no explicit tenant is passed. Details — [Multi-tenancy](usage/multi-tenancy.md).

> [!WARNING]
> A closure (`fn () => ...`) works but breaks `php artisan config:cache`: Laravel can't serialize closures in config. Use an array callable or a `'Class::method'` string. The callable must return a `string` or `null` — the manager is `strict_types`, an integer id throws a `TypeError`.

## types

Same array shape as `typeDefinition()`. Registered after discovery, so a config entry with the same `key` replaces the discovered definition. See [Notify types](usage/notify-types.md).

## discover

Every listed directory is scanned recursively on each application boot. See [Notify types — auto-discovery](usage/notify-types.md#auto-discovery).

## models

Point a key to your subclass to add behaviour, for example translations on `NotifyTemplate` ([Multilingual templates](usage/translations.md)). The manager and the log subscriber resolve the class from this config; your own queries should use the same class.

## log

```php
'log' => [
    'enabled' => false,
    'retention_days' => 90,
    'external_id_resolvers' => [
        'mail' => \Fomvasss\NotifyTemplates\Resolvers\MailMessageIdResolver::class,
        'telegram' => \Fomvasss\NotifyTemplates\Resolvers\TelegramMessageIdResolver::class,
    ],
    'content_resolvers' => [
        'mail' => \Fomvasss\NotifyTemplates\Resolvers\MailContentResolver::class,
        'telegram' => \Fomvasss\NotifyTemplates\Resolvers\TelegramContentResolver::class,
    ],
    'store_body' => true,
],
```

`log.enabled` is read once, when the service provider boots: the event subscriber is registered then or not at all. Changing the value at runtime has no effect. The resolver maps are keyed by the channel exactly as `via()` returned it — `'mail'`, `'telegram'` or a channel class name. Details — [Delivery log](usage/delivery-log.md).
