# Upgrading

Changes that can affect existing code. The full list is in [CHANGELOG](https://github.com/fomvasss/laravel-notify-templates/blob/master/CHANGELOG.md).

After every upgrade:

```bash
php artisan vendor:publish --tag=notify-templates-migrations   # publishes only migrations you don't have
php artisan migrate
```

and compare your published `config/notify-templates.php` with the package's: nested arrays (`tables`, `models`, `log`) are not merged, so new keys must be copied in by hand.

## Unreleased — slot parameter of the messenger helpers

`getMessengerBody()`, `getMessengerButtons()` and `getMessengerButtonsColumns()` take an optional `$slot` (see [per-channel text](usage/templates.md#per-channel-text)). PHP requires an override to accept it too, otherwise the class fails to load:

```php
// before
protected function getMessengerBody(mixed $notifiable): string
// after
protected function getMessengerBody(mixed $notifiable, string $slot = 'messenger'): string
```

Same for `getMessengerButtons(mixed $notifiable, string $slot = 'messenger')` and `getMessengerButtonsColumns(string $slot = 'messenger')`. Without per-channel slots in `channel_options` the value is always `'messenger'` and the override can ignore it.

New config keys `channel_options` and `slot_options` are optional; a published config works without them.

## 0.12.4 — discovered keys are checked

Discovery now throws `LogicException` at boot for a class whose `typeDefinition()['key']` differs from its `notifyKey()` — such a type already lost its settings, `user_configurable` and buttons at send time. Run `php artisan about` (or any command) locally before deploying: the message names the class. Fix it by overriding `notifyKey()` to return the stored key, so rows in `notify_*` tables keep matching.

## 0.12.3 — default_channels fallback

The `default_channels` fallback of non-configurable types now goes through `mapChannel()`, like every other channel. If you put a channel class there (`TurboSmsChannel::class`), it is now dropped: put a slug instead and map it in `mapChannel()`.

## 0.11 / 0.12 — messenger buttons

No migration. New optional `typeDefinition()` keys `buttons`, `buttons_by_role`, `buttons_columns`, and template `options.buttons`. Button text may be a locale map since 0.12. `TelegramContentResolver` now appends url buttons to the logged body.

## 0.9 — subject and body in the log

- Publish the migrations: installs that created `notify_logs` on 0.8.x get `add_content_to_notify_logs_table`.
- A published config needs the new `log.content_resolvers` and `log.store_body` keys, and `telegram` in `log.external_id_resolvers`.

## 0.8 — delivery log

- New migration `create_notify_logs_table`, new config keys `tables.notify_logs`, `models.notify_log`, `log.*`. The log is off until `log.enabled = true`.
- `NotifyTemplatesManager::resolveTenantId()` is public. A subclass that overrides it must widen its visibility.

## 0.7 — mapChannel()

If your app copied `via()` to add channels, move that logic into `mapChannel()` and delete the copy. The copy misses the 0.6 changes below and every later fix to the resolution chain. See [Custom channels](usage/custom-channels.md).

## 0.6 — empty resolution means "don't send"

- **Behaviour change:** `default_channels` is no longer used when `via()` resolves to nothing — except for types with `'user_configurable' => false`. A regular type without an active subscription, or with every channel opted out, is not sent. Before, it went out by mail. Create subscriptions for the types you rely on, or mark guaranteed types non-configurable.
- `notify_user_settings.channels = []` now really disables the type for that notifiable.
- `notifyKey()` and `notify:make` strip only the trailing `Notify` (`MyNotifyDigestNotify` → `MyNotifyDigest`, was `MyDigest`). Check classes with `Notify` in the middle of the name: their key changed, and stored rows must be renamed — or override `notifyKey()`.

## 0.5 — non-configurable types

New optional `typeDefinition()` key `user_configurable`. No action needed.

## 0.4 — per-type user settings

New table `notify_user_settings` — it is in `create_notifytemplates_tables`. If you published that migration before 0.4, the publish command skips it (a file with that name exists): create the table with a migration of your own, copying the `notify_user_settings` block from the package stub. New config keys `tables.notify_user_settings`, `models.notify_user_setting`.

## 0.2.6

- `default_channel` (string) → `default_channels` (array).
- Column `is_personal` → `personal_only` on `notify_role_subscriptions`.
- User channels from `getNotifyChannels()` are intersected with subscription channels instead of merged: a user can't add channels.
- `config('notify-templates.tenant_id')` is now applied when no tenant is passed. If you set it, check that code relying on global rows still gets them.
