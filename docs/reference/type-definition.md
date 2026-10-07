# typeDefinition() keys

The array returned by `BaseNotify::typeDefinition()`, passed to `registerType()`, or listed in `config('notify-templates.types')`.

| Key | Type | Default | Used by | Description |
|---|---|---|---|---|
| `key` | `string` | the class's `notifyKey()` (discovery) | registry | Unique type key, e.g. `OrderOrdered`. Required in `registerType()` and `config('notify-templates.types')`; a discovered class may omit it. Must equal the class's `notifyKey()` — discovery throws `LogicException` otherwise |
| `name` | `string` | — | your UI | Human-readable label |
| `group` | `string` | — | `getTypes($group)`, your UI | Group, e.g. `order` |
| `weight` | `int` | — | your UI | Sort order within a group; lower first. The registry doesn't sort |
| `desc` | `string` | — | your UI | Description, e.g. a tooltip |
| `settings` | `string[]` | — | your UI, `resolveDelay()` | Option keys editable per subscription, stored in `notify_role_subscriptions.options`. The package reads `delay` (minutes) |
| `tokens` | `array` | — | your UI | Token hints: `[['key' => '[order:number]', 'name' => 'Number']]` |
| `channels` | `string[]` | `[]` | `getTypeChannels()` | Channel slugs offered for this type in the UI; empty = `config('notify-templates.channels')`. Not used when sending |
| `defaults` | `array` | — | sending, your UI | `defaults.mail.subject` / `defaults.mail.body` are the fallback when no template matches; other slots are editor placeholders only |
| `user_configurable` | `bool` | `true` | `via()` | `false`: user opt-outs and per-type channels are ignored, and an empty resolution falls back to `default_channels`. See [User preferences](../usage/user-preferences.md#non-configurable-types) |
| `log_body` | `bool` | `true` | delivery log | `false`: the log stores the subject but never the body |
| `buttons` | `array` | `[]` | buttons | `[['text' => 'Pay', 'url' => '[order:payUrl]']]`; `text` may be a locale map. See [Messenger buttons](../usage/messenger-buttons.md) |
| `buttons_by_role` | `array` | — | buttons | `['admin' => [...]]` replaces `buttons` for a role; `[]` = no buttons |
| `buttons_columns` | `int` | `1` | buttons | Buttons per row |

Any other key is stored as is and returned by `getType()`; the package ignores it. See [custom keys](../usage/notify-types.md#custom-keys).

`user_configurable` is read from the registry by key; `defaults`, `log_body` and the button keys are read from the class's own `typeDefinition()` when sending.

## Example

```php
public static function typeDefinition(): array
{
    return [
        'key' => 'OrderOrdered',
        'name' => 'Order placed',
        'group' => 'order',
        'weight' => 20,
        'desc' => 'Sent when the customer places an order',
        'channels' => ['mail', 'telegram'],
        'settings' => ['delay'],
        'tokens' => [
            ['key' => '[order:number]', 'name' => 'Order number'],
            ['key' => '[order:payUrl]', 'name' => 'Payment link'],
        ],
        'defaults' => [
            'mail' => ['subject' => 'Order placed', 'body' => 'Your order [order:number] has been received.'],
            'messenger' => ['body' => 'New order [order:number]'],
        ],
        'buttons' => [
            ['text' => ['uk' => 'Оплатити', 'en' => 'Pay'], 'url' => '[order:payUrl]'],
        ],
        'buttons_by_role' => ['admin' => []],
    ];
}
```
