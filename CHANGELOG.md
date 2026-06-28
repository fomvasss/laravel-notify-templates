# Changelog

## [Unreleased]

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
