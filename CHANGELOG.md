# Changelog

## [Unreleased]

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
