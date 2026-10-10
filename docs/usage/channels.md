# Channel resolution

`BaseNotify::via()` runs the same chain for every notifiable. Each step can only take channels away; nothing added later can bring back what an earlier step removed.

```
0. Opt-out          NotifyTemplates::isNotifyEnabled($key, $notifiable)
                    notify_user_settings.is_enabled = false → via() returns []
                    'user_configurable' => false → always enabled, the row is ignored
        ↓
1. Global choice    $notifiable->getNotifyChannels()  (optional method)
                    missing method or [] → no restriction
        ↓
2. Per-type choice  NotifyTemplates::resolveNotifyUserChannels($key, $notifiable)
                    null → nothing more; an array narrows step 1
                    nothing left ([] stored, or no overlap with step 1) → via() returns []
                    'user_configurable' => false → always null
        ↓
3. Subscription     notify_role_subscriptions for role + type (+ tenant)
                    no row or is_active = false → []
                    channels empty → config('notify-templates.default_channels')
                    intersected with steps 1–2
        ↓
4. Mapping          mapChannel($slug, $notifiable) → channel name / class, or null to drop
                    built-in: mail needs a mail route (routeNotificationFor('mail'), else ->email); database, broadcast pass; anything else is dropped
        ↓
5. Guarantee        nothing left and 'user_configurable' => false → config('notify-templates.default_channels')
        ↓
6. Call site        ->only([...]) then ->except([...])
```

Steps 1–3 work with channel slugs (`mail`, `telegram`, `sms`); steps 5–6 with what `mapChannel()` returned.

`typeDefinition()['channels']` and `config('notify-templates.channels')` are not part of this chain. They are the list of checkboxes in your subscription form (`getTypeChannels()`); labels, icons and the template slot of each channel come from [`channel_options`](../configuration.md#channel_options) via `getChannels()`.

## Scenarios

| Setup | Channels |
|---|---|
| No subscription | none (a non-configurable type: `default_channels`) |
| Subscription inactive | none (a non-configurable type: `default_channels`) |
| Active subscription, `channels` empty | `default_channels`, then mapped |
| Subscription `mail, telegram`, user has no Telegram route | `mail` |
| Subscription `mail, telegram`, `getNotifyChannels()` returns `['mail']` | `mail` |
| Subscription `mail, telegram`, per-type setting `['telegram']` | `telegram` for this type only |
| Per-type setting `[]` | none |
| `is_enabled = false` for a type with `'user_configurable' => false` | sent — the row is ignored |
| Subscription `mail`, `->only(['telegram'])` | none — `only()` doesn't add channels |
| Subscription `mail, database`, `->except(['database'])` | `mail` |

## The guaranteed-delivery fallback

For regular types an empty result means "don't send", and that is final: a user's opt-out or an inactive subscription is never overridden.

A type with `'user_configurable' => false` (login codes, security alerts) falls back to `default_channels` when nothing survived — no subscription, inactive subscription, or no route found by `mapChannel()`. The fallback values go through `mapChannel()` like resolved ones (since 0.12.3): with the default `['mail']` and a notifiable without a mail route nothing is sent, and your own slugs need a `mapChannel()` entry.

> [!NOTE]
> Before 0.6.0 every type fell back to `default_channels` when the result was empty. See [Upgrading](../upgrading.md).

## Channels from the user's side

`getNotifyChannels()` is a method you may define on the notifiable (or get from the `HasNotifySettings` trait) returning the slugs the user accepts. It can only remove channels from the subscription, not add any. See [User preferences](user-preferences.md).

## Checking the result

`via()` is public, so a test or a debug route can see what a notifiable would get:

```php
(new OrderOrderedNotify($order, 'client'))->via($user); // ['mail', 'telegram']
```

The manager methods behind the steps are public too: `isNotifyEnabled()`, `resolveNotifyUserChannels()`, `resolveChannels()` — see the [facade reference](../reference/facade.md).
