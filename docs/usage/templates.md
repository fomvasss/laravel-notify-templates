# Templates

`notify_templates` holds the subject and body that admins edit. A row is keyed by notify type, channel slot, role and tenant; any of the last three may be `null`, meaning "any".

| Column | Meaning |
|---|---|
| `notify_key` | Notify type key |
| `channel` | Template slot: `mail`, `messenger`, or your own; `null` = any slot |
| `role_key` | `null` = any role |
| `tenant_id` | `null` = any tenant |
| `subject` | Used by mail |
| `body` | The text, with your tokens |
| `options` | JSON — e.g. [messenger buttons](messenger-buttons.md) |

There is at most one row per combination (unique index `nt_lookup` over the key with `COALESCE` on the nullable columns).

Example data:

| notify_key | channel | role_key | tenant_id | subject | body |
|---|---|---|---|---|---|
| OrderOrdered | mail | null | null | Order placed | Your order [order:number] has been received. |
| OrderOrdered | mail | client | shop-ua | Thank you for your order | Hi [user:name]! Order [order:number]… |
| OrderOrdered | messenger | null | null | null | Order [order:number] placed |

## Slots

The slot is not the delivery channel — several channels can share one slot.

| Slot | Used by |
|---|---|
| `mail` | `toMail()`; also the fallback of `getMessengerBody()` |
| `messenger` | `getMessengerBody()` — `toArray()` (database/broadcast) and your Telegram, SMS, … methods; [buttons](messenger-buttons.md) |
| a channel's own slot (`telegram`, `sms`, …) | `getMessengerBody($notifiable, $slot)` — overrides `messenger`, see below |
| anything else | Your own code: `$this->resolveTemplate('push')` inside the Notify class |

### Per-channel text

All messengers share the `messenger` text by default. To give one channel its own text, set its slot in [`channel_options`](../configuration.md#channel_options) and pass that slot when building the message:

```php
// config/notify-templates.php
'channel_options' => [
    'telegram' => ['slot' => 'telegram'],
],

// your base notification
public function toTelegram(mixed $notifiable): TelegramMessage
{
    $slot = NotifyTemplates::getChannel('telegram')['slot'];

    return TelegramMessage::create()->line($this->getMessengerBody($notifiable, $slot));
}
```

The `telegram` row is an override, not a copy: `getMessengerBody()` reads it only when it has a body, otherwise the `messenger` row, then the `mail` row, then `defaults.mail.body`. Buttons follow the same order per option — a `telegram` row without `options.buttons` keeps the buttons of the `messenger` row. Rows with `channel = null` are never taken for an override. Without the config entry the slot is `messenger` and nothing changes.

## Fallback chain

`NotifyTemplates::resolveTemplate('OrderOrdered', 'mail', 'client', 'shop-ua')` returns the most specific matching row. Specificity: a matching slot outweighs a matching role, which outweighs a matching tenant.

| # | channel | role_key | tenant_id |
|---|---|---|---|
| 1 | mail | client | shop-ua |
| 2 | mail | client | null |
| 3 | mail | null | shop-ua |
| 4 | mail | null | null |
| 5 | null | client | shop-ua |
| 6 | null | client | null |
| 7 | null | null | shop-ua |
| 8 | null | null | null |

Rows of another role or tenant never match. When the role or tenant is `null`, only rows with `null` there match. If nothing matches, the result is `null`.

The tenant, when not passed, comes from `config('notify-templates.tenant_id')` — see [Multi-tenancy](multi-tenancy.md).

## Defaults in code

When no row matches, or the matching row has an empty subject or body, `BaseNotify` uses the type's defaults:

```php
'defaults' => [
    'mail' => ['subject' => 'Order placed', 'body' => 'Your order [order:number] has been received.'],
    'messenger' => ['body' => 'New order [order:number]'],
],
```

| Method | Returns |
|---|---|
| `getSubjectDefault()` | `defaults.mail.subject` or `''` |
| `getBodyDefault()` | `defaults.mail.body` or `''` |

> [!NOTE]
> `defaults.messenger` is not used when sending — it is metadata for the editor. Without a `messenger` or `mail` row, `getMessengerBody()` falls back to `defaults.mail.body`. Store a `messenger` row (or override `getMessengerBody()`) if messengers need a different default text.

Fields are checked separately: a `mail` row with a body and an empty subject is sent with `defaults.mail.subject`.

## Tokens

The package substitutes nothing — `prepareText()` returns the text unchanged. Override it in the Notify class, or once in your base notification class:

```php
protected function prepareText(string $text, mixed $notifiable): string
{
    return strtr($text, [
        '[order:number]' => $this->order->number,
        '[user:name]' => $notifiable->name ?? '',
    ]);
}
```

or with [fomvasss/laravel-str-tokens](https://github.com/fomvasss/laravel-str-tokens):

```php
protected function prepareText(string $text, mixed $notifiable): string
{
    return \StrToken::setEntity($notifiable)->setText($text)->replace();
}
```

`prepareText()` is applied to the mail subject and body, to `getMessengerBody()`, and to button text and urls. `typeDefinition()['tokens']` is only a list for the editor — keep it in sync with what `prepareText()` replaces.

## Editing templates

The package has no admin UI. Create and update rows with the model (or the class from `config('notify-templates.models.notify_template')`):

```php
use Fomvasss\NotifyTemplates\Models\NotifyTemplate;

NotifyTemplate::updateOrCreate(
    ['notify_key' => 'OrderOrdered', 'channel' => 'mail', 'role_key' => 'client', 'tenant_id' => null],
    ['subject' => 'Order placed', 'body' => 'Your order [order:number] has been received.'],
);
```

To preview what a recipient gets, resolve the row the same way sending does:

```php
NotifyTemplates::resolveTemplate('OrderOrdered', 'mail', 'client')?->subject
    ?: OrderOrderedNotify::typeDefinition()['defaults']['mail']['subject'];
```
