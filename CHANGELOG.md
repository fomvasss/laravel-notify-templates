# Changelog

## [Unreleased]

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
