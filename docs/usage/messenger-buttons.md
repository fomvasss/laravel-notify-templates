# Messenger buttons

A long link reads badly in a messenger text; a button under the message reads better. The package resolves buttons per type, role and template — rendering them is up to your `toTelegram()` (or another messenger method). Available since 0.11.0.

## Defining buttons

In `typeDefinition()`:

```php
'buttons' => [
    ['text' => 'Pay', 'url' => '[order:payUrl]'],
],
'buttons_by_role' => [
    'admin' => [['text' => 'Order in admin', 'url' => '[order:adminUrl]']],
    'guest' => [], // no buttons for this role
],
'buttons_columns' => 1,
```

Tokens are allowed in both `text` and `url`. An admin can override the buttons for one template in the `options` JSON of a `messenger` row:

```json
{"buttons": [{"text": "Pay now", "url": "[order:payUrl]"}], "buttons_columns": 2}
```

## Resolution

Buttons: template `options.buttons` → `buttons_by_role[role]` → `buttons` → none.

Columns: template `options.buttons_columns` → `buttons_columns` → `1` (never less than 1).

- An array wins even when empty: `"buttons": []` in a template, or `'guest' => []` in `buttons_by_role`, means no buttons. Leave the key out to fall through to the next level.
- The template is found through the normal [fallback chain](templates.md#fallback-chain) for the `messenger` slot, so a row with `channel = null` can provide buttons too.
- Entries without text or url are skipped.

## Rendering

```php
public function toTelegram(mixed $notifiable): TelegramMessage
{
    $message = TelegramMessage::create()
        ->options(['parse_mode' => 'HTML'])
        ->line($this->getMessengerBody($notifiable));

    $columns = $this->getMessengerButtonsColumns();

    foreach ($this->getMessengerButtons($notifiable) as $button) {
        $message->button($button['text'], $button['url'], $columns);
    }

    return $message;
}
```

`getMessengerButtons()` returns `[['text' => ..., 'url' => ...], ...]` with the text localized and both fields passed through `prepareText()`. Then it drops every button whose url is not sendable: one bad url (an unresolved token, a `.test` host) makes Telegram reject the whole message, not just the button.

A url is sendable when it is a valid absolute `http`/`https` url and its host contains a dot and doesn't end in `.test`, `.local` or `.localhost`. To allow a tunnel or a local host during development, override the check:

```php
protected function isSendableButtonUrl(string $url): bool
{
    return app()->isLocal() ? str_starts_with($url, 'http') : parent::isSendableButtonUrl($url);
}
```

Channels without buttons (SMS, plain-text WhatsApp) can append the links to the text instead.

With the [delivery log](delivery-log.md) on, `TelegramContentResolver` appends url buttons to the logged body as `[text] url` lines.

## Multilingual text

`text` can be a locale map instead of a string — in `typeDefinition()` and in template `options` alike. The url is shared (since 0.12.0):

```php
'buttons' => [
    ['text' => ['uk' => 'Оплатити', 'en' => 'Pay'], 'url' => '[order:payUrl]'],
],
```

The text for the current locale is taken, then `app.fallback_locale`, then the first non-empty entry; empty entries count as missing. Laravel switches the locale per notifiable that implements `HasLocalePreference` (or with `->locale()`), so each recipient gets their language.

For an admin UI, the same logic is available directly:

```php
NotifyTemplates::localizeButtonText(['uk' => 'Оплатити', 'en' => 'Pay'], 'en'); // 'Pay'
NotifyTemplates::resolveButtons('OrderOrdered', 'client');        // raw, tokens not substituted
NotifyTemplates::resolveButtonsColumns('OrderOrdered', 'client');
```

`options` lives on `notify_templates` itself, not in a translation table, so the locale map is also the way to translate buttons when templates are translated with astrotomic (see [Multilingual templates](translations.md)).
