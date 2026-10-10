# Changelog

## [Unreleased]

### Changed
- **Breaking for subclasses:** `getMessengerBody()`, `getMessengerButtons()` and `getMessengerButtonsColumns()` got an optional `string $slot = 'messenger'` parameter. An override without it fails with "Declaration must be compatible" — add the parameter to the override, see [Upgrading](docs/upgrading.md)

### Added
- `channel_options` config key: per-channel `label`, template `slot` and any extra keys (icon, …) for an admin UI
- `slot_options` config key: per-slot `label`, `subject` and any extra keys (rows, max length, …) for the template edit form and preview
- `getSlots()` and `getSlot()` on the manager and facade
- Per-channel messenger text: `getMessengerBody()`, `getMessengerButtons()` and `getMessengerButtonsColumns()` take an optional `$slot`. A channel with its own slot (`channel_options.telegram.slot = 'telegram'`) reads its row when it has a body, otherwise the shared `messenger` row; buttons fall back per option the same way. `resolveMessengerTemplate()` on the manager. Without a slot argument nothing changes
- `getChannels()`, `getChannel()` and `getTypeSlots()` on the manager and facade — which channels exist, how to show them and which template slots a type renders, without hard-coding `if ($channel === 'messenger')` in the UI

## [0.12.6] - 2026-10-07

### Fixed
- `tenant_id` from the config (or its callable) as an integer threw a `TypeError`; it is now cast to a string
- A configured `tenant_id` string that is also a PHP function name (`date`, `max`) was called as a callable. Only closures, array callables and `'Class::method'` strings are called now
- There was no way to ask the manager for global rows only while a tenant is configured. An empty `$tenantId` (`''`) now means "global", skipping the config fallback

## [0.12.5] - 2026-10-07

### Fixed
- The default `toMail()` put the whole body into one `line()`, so a multi-paragraph template arrived as a single paragraph with its line breaks joined. Each paragraph (separated by a blank line) is now its own line and line breaks become `<br>`; HTML is still escaped

## [0.12.4] - 2026-10-07

### Fixed
- A discovered notify class whose `typeDefinition()['key']` differed from `notifyKey()` was registered under one key and sent under the other, so its settings, `user_configurable` and buttons were silently not found. Discovery now throws a `LogicException` naming the class; a discovered class may omit `key` to take `notifyKey()`

## [0.12.3] - 2026-10-07

### Fixed
- The `default_channels` fallback of `'user_configurable' => false` types skipped `mapChannel()`: `mail` was returned for a notifiable without a mail route, and host channel slugs reached Laravel unmapped. The fallback now maps them like resolved channels. A channel class written directly into `default_channels` is dropped now — use a slug and `mapChannel()`

## [0.12.2] - 2026-10-07

### Fixed
- `mail` was dropped for on-demand recipients (`Notification::route('mail', ...)`) and for models whose address comes from `routeNotificationForMail()`: only `$notifiable->email` was checked. The mail route is checked now. A `mapChannel()` override written as a workaround can be removed

## [0.12.1] - 2026-10-07

### Fixed
- `migrate` on a fresh install failed with "table notify_logs doesn't exist": the published migrations shared one timestamp and `add_content_to_notify_logs_table` ran before `create_notify_logs_table`. They now get increasing timestamps. Migrations published by an older version keep their names — delete `*_add_content_to_notify_logs_table.php` if it has not run yet
- Rolling back `add_content_to_notify_logs_table` no longer drops `notify_logs.subject` / `body`, which on a fresh install belong to `create_notify_logs_table`

## [0.12.0] - 2026-09-25

### Added
- Multilingual button text: `text` in `buttons` / `buttons_by_role` / template `options.buttons` may be a locale map (`['uk' => 'Оплатити', 'en' => 'Pay']`), resolved to the current locale, then `app.fallback_locale`, then the first non-empty entry. Plain strings work as before
- `NotifyTemplates::localizeButtonText($text, $locale = null)`

## [0.11.0] - 2026-09-25

### Added
- Messenger link buttons: `typeDefinition()` keys `buttons`, `buttons_by_role` and `buttons_columns`, overridable per template through `notify_templates.options.buttons` / `options.buttons_columns` (no migration needed). `"buttons": []` in a template removes the type's buttons
- `BaseNotify::getMessengerButtons($notifiable)` (tokens substituted, unsendable urls dropped) and `getMessengerButtonsColumns()` for the host's `toTelegram()`; override `isSendableButtonUrl()` to change which urls count as sendable
- `NotifyTemplates::resolveButtons()` / `resolveButtonsColumns()` — raw buttons for admin UIs

### Changed
- `TelegramContentResolver` appends url buttons to the logged body as `[text] url` lines

## [0.10.1] - 2026-09-21

### Fixed
- `NotifyLog::statuses()` / `statusLabels()` list statuses in lifecycle order (`pending`, `sent`, `delivered`, `read`, `failed`); `failed` used to come before `read`

## [0.10.0] - 2026-09-21

### Added
- Translatable delivery status labels: `NotifyLog::statusLabels()` and `$log->getStatusLabel()`, `en` and `uk` included. Publish with `php artisan vendor:publish --tag=notify-templates-lang` to change the wording or add a locale

## [0.9.0] - 2026-09-21

### Added
- `notify_logs.subject` / `notify_logs.body` — what was actually sent, tokens substituted, taken from the channel's response. Existing 0.8.x installs: `php artisan vendor:publish --tag=notify-templates-migrations` publishes `add_content_to_notify_logs_table`
- `ContentResolverInterface` + `log.content_resolvers` config; built-in `MailContentResolver` (subject + HTML) and `TelegramContentResolver` (message text, chunked parts joined)
- `TelegramMessageIdResolver` — Bot API `message_id` as `external_id`, bound for `telegram` by default
- `log.store_body` (default `true`) and `typeDefinition()['log_body'] = false` to keep the body out of the log (OTP codes, passwords); the subject is still stored

### Changed
- A published config must carry the new `log.content_resolvers` / `log.store_body` keys — `mergeConfigFrom()` does not merge nested arrays

## [0.8.1] - 2026-09-21

### Fixed
- `updateDelivery()` picked the most recent log row by `id`, which is random order when the host app uses UUID keys; now by `created_at`

## [0.8.0] - 2026-09-21

### Added
- Delivery log (opt-in, `log.enabled`): table `notify_logs`, one row per notification × channel × recipient, written from `NotificationSending`/`Sent`/`Failed` for `BaseNotify` subclasses. Existing installs: `php artisan vendor:publish --tag=notify-templates-migrations` publishes only the new `create_notify_logs_table` migration
- Delivery statuses `pending`/`sent`/`delivered`/`read`/`failed` and `NotifyTemplates::updateDelivery($channel, $externalId, $status, $payload)` for provider delivery reports. The status only moves forward, so out-of-order reports are ignored
- `ExternalIdResolverInterface` + `log.external_id_resolvers` config: extracts the provider message id from a channel's send() response. `MailMessageIdResolver` is bound for `mail` by default
- `NotifyLog` is `MassPrunable`: rows older than `log.retention_days` (default 90) are removed by `model:prune`
- `BaseNotify::getRoleKey()`, `BaseNotify::getTenantId()`
- New config keys: `tables.notify_logs`, `models.notify_log`, `log.*`

### Changed
- `NotifyTemplatesManager::resolveTenantId()` is now public (was protected). Subclasses that override it must widen the visibility too

## [0.7.0] - 2026-08-16

### Added
- `mapChannel(string $channel, mixed $notifiable): ?string` — protected hook on `BaseNotify`, THE extension point for host channels: map a subscription channel slug to a channel name/class-string (or `null` to skip). Override it in your app's base notification class instead of copying `via()` — the opt-out gate, user channel preferences, subscription resolution, the `user_configurable` fallback and `only()`/`except()` keep applying to your channels automatically. Built-in mapping (mail with the email check, database, broadcast) is unchanged
- README (en+uk): "Extending in the host app" section with a full host base-class example (mapChannel, `to{Channel}()` via `getMessengerBody()`, `prepareText()` tokens, custom mail view) and an explicit warning against copying `via()`

## [0.6.0] - 2026-08-16

### Fixed
- `notify_user_settings.channels = []` (notifiable disabled every channel for a type) now actually suppresses delivery: `via()` returns `[]` instead of falling through to `resolveChannels()`, which treated the empty array as "no preference" and returned the full subscription channel list. Same for an override that has no overlap with the notifiable's global channel preference
- `notifyKey()` and `notify:make` now strip only the trailing `Notify` suffix from the class name instead of every occurrence (`MyNotifyDigestNotify` → `MyNotifyDigest`, was `MyDigest`)

### Changed
- **Behavior change**: the `default_channels` fallback in `via()` now applies only to types with `'user_configurable' => false` (guaranteed delivery for OTP and the like). For regular types an empty resolution — no/inactive subscription, or user opt-outs leaving nothing — means "don't send" and is no longer silently overridden with mail. If your host app duplicates `via()` instead of calling `parent::via()`, sync this change there too

## [0.5.1] - 2026-08-14

### Fixed
- README "Channel resolution flow" corrected and brought up to date: `typeDefinition()['channels']` was never actually part of the `via()` resolution chain (it only drives the admin UI's channel checkboxes) — the doc previously listed it as step 1, which was wrong. Documented the `isNotifyEnabled()` / `resolveNotifyUserChannels()` steps (added in 0.4.0/0.5.0) that were missing from this section

## [0.5.0] - 2026-08-14

### Added
- `typeDefinition()['user_configurable'] = false` — mark a notify type as never opt-out-able or channel-restrictable by the notifiable (e.g. OTP/security codes), even if a `notify_user_settings` row exists for it. Default `true` (unchanged behavior for existing types)
- `NotifyTemplatesManager::isUserConfigurable(string $notifyKey): bool`, also on the `NotifyTemplates` facade — use it to filter such types out of a settings-form toggle list

## [0.4.0] - 2026-08-14

### Added
- Notifiables can opt out of a specific notify type, and/or restrict that type to a subset of delivery channels — applies automatically, no changes needed in your existing Notify classes
- New table `notify_user_settings` (works with any Eloquent model, not just `User`) and model `NotifyUserSetting`
- `NotifyTemplatesManager::isNotifyEnabled()` / `resolveNotifyUserChannels()` — also available on the `NotifyTemplates` facade and the `HasNotifySettings` trait
- New config keys: `tables.notify_user_settings`, `models.notify_user_setting`

### Changed
- Existing installs need the new table: `php artisan vendor:publish --tag=notify-templates-migrations` (only runs if you haven't already published the migration — see README to add it manually otherwise)

## [0.2.6] - 2026-08-02

### Added
- `only(array $channels)` and `except(array $channels)` fluent methods on `BaseNotify` to override channels at call site
- `default_channels` config key (array) — fallback channels when subscription has no channels configured or `via()` resolves to nothing
- `personal_only` column on `notify_role_subscriptions` — per role+notify flag to send only to the context user

### Changed
- `discoverIn()` now recurses into subdirectories (uses `RecursiveDirectoryIterator`)
- User channels (`getNotifyChannels()`) are now **intersected** with subscription channels instead of merged — user can opt out of channels but not add new ones
- `default_channel` config key renamed to `default_channels` (array)
- `is_personal` column renamed to `personal_only` in migration and model

### Fixed
- `registerType()` now throws `InvalidArgumentException` when `key` is missing
- `config('notify-templates.tenant_id')` is now actually used: `NotifyTemplatesManager::resolveTemplate()`/`resolveChannels()`/`resolveDelay()` fall back to it (string or callable) whenever no explicit `$tenantId` is passed — previously the config key was documented but never read
