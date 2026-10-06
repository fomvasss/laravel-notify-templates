# User preferences

A notifiable can restrict what it receives in three ways, all checked in `via()` (see [Channel resolution](channels.md)):

| Preference | Stored in | Effect |
|---|---|---|
| Global channels | your own column, read by `getNotifyChannels()` | Only these channels, for every type |
| Type off | `notify_user_settings.is_enabled = false` | Nothing of this type |
| Type channels | `notify_user_settings.channels` | Only these channels for this type, within the global ones |

None of them can add a channel the role subscription doesn't have.

## Global channels — HasNotifySettings

```php
use Fomvasss\NotifyTemplates\Traits\HasNotifySettings;

class User extends Authenticatable
{
    use HasNotifySettings, Notifiable;

    protected $casts = [
        'notify_channels' => 'array',
    ];
}
```

The trait's `getNotifyChannels()` returns the `notify_channels` attribute, or `[]`. The package doesn't ship a migration for this column — add it to your users table, or override the method:

```php
public function getNotifyChannels(): array
{
    return $this->channels ?? [];
}
```

The trait is optional: `via()` calls `getNotifyChannels()` on any notifiable that has the method. A missing method and an empty array both mean "no restriction" — not "no channels". To let a user turn everything off, use the per-type settings below.

The trait also adds `$user->isNotifyEnabled($notifyKey)`.

## Per-type settings — notify_user_settings

One optional row per notifiable and notify type:

| Column | Meaning |
|---|---|
| `notifiable_type`, `notifiable_id` | Morph pair of any Eloquent model; the id is a string, so UUID keys work |
| `notify_key` | Notify type |
| `is_enabled` | `false` = the notifiable doesn't receive this type |
| `channels` | `null` = no override; an array = only these slugs for this type |

No row means enabled with no override. Rows exist only because something recorded an explicit choice, typically a profile form:

```php
use Fomvasss\NotifyTemplates\Models\NotifyUserSetting;

NotifyUserSetting::updateOrCreate(
    ['notifiable_type' => $user->getMorphClass(), 'notifiable_id' => $user->getKey(), 'notify_key' => 'OrderOrdered'],
    ['is_enabled' => true, 'channels' => ['telegram']],
);
```

The per-type channels narrow the global ones (intersection). If the result is empty — `channels = []`, or no overlap with `getNotifyChannels()` — the type is not sent at all.

Reading the state for a form:

```php
NotifyTemplates::isNotifyEnabled('OrderOrdered', $user);          // bool
NotifyTemplates::resolveNotifyUserChannels('OrderOrdered', $user); // ?array
$user->isNotifyEnabled('OrderOrdered');                           // with HasNotifySettings
```

Notifiables that aren't Eloquent models (on-demand recipients) are always enabled with no override.

## Non-configurable types

Some types must reach the user no matter what — a login code the user can't turn off without locking themselves out. Mark them in `typeDefinition()`:

```php
public static function typeDefinition(): array
{
    return [
        'key' => 'UserOtp',
        'name' => 'Login code',
        'group' => 'user',
        'user_configurable' => false, // default true
        'log_body' => false,           // keep the code out of the delivery log
    ];
}
```

With `'user_configurable' => false`:

- `isNotifyEnabled()` always returns `true` and `resolveNotifyUserChannels()` always `null`, whatever `notify_user_settings` holds. This is enforced at send time, not only hidden in the UI.
- When resolution leaves nothing — no subscription, an inactive one, or no route — `via()` falls back to `default_channels`.
- The global `getNotifyChannels()` **still applies** in step 1 of resolution. If a user's global channels exclude every channel of the subscription, the result is empty and the `default_channels` fallback sends it anyway.

Hide such types from a profile form with `NotifyTemplates::isUserConfigurable($key)`:

```php
$types = collect(NotifyTemplates::getTypes())
    ->filter(fn ($type, $key) => NotifyTemplates::isUserConfigurable($key));
```

`user_configurable` is read from the registry by `notifyKey()`, so the type must be registered (discovered or registered manually) under the same key.
