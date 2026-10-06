# Custom channels

`BaseNotify` delivers `mail`, `database` and `broadcast` out of the box. Other channels — Telegram, SMS gateways, WhatsApp — are added in the host app, usually in one abstract base class that every Notify extends:

```php
namespace App\Notifications;

use App\Channels\TurboSmsChannel;
use Fomvasss\NotifyTemplates\Notifications\BaseNotify;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;
use NotificationChannels\Telegram\TelegramMessage;

abstract class BaseNotification extends BaseNotify
{
    // 1. Map subscription slugs to Laravel channels; null drops the channel for this notifiable.
    protected function mapChannel(string $channel, mixed $notifiable): ?string
    {
        return match ($channel) {
            'telegram' => $notifiable->routeNotificationForTelegram() ? 'telegram' : null,
            'sms' => $notifiable->phone ? TurboSmsChannel::class : null,
            default => parent::mapChannel($channel, $notifiable),
        };
    }

    // 2. One to{Channel}() per added channel.
    public function toTelegram(mixed $notifiable): TelegramMessage
    {
        return TelegramMessage::create()
            ->options(['parse_mode' => 'HTML'])
            ->line($this->getMessengerBody($notifiable));
    }

    public function toTurboSms(mixed $notifiable): string
    {
        return Str::limit(strip_tags($this->getMessengerBody($notifiable)), 660);
    }

    // 3. Token substitution for every channel.
    protected function prepareText(string $text, mixed $notifiable): string
    {
        return \StrToken::setEntity($notifiable)->setText($text)->replace();
    }
}
```

The method name Laravel calls depends on the channel class; follow the convention of the channel package you use.

## mapChannel()

```php
protected function mapChannel(string $channel, mixed $notifiable): ?string
```

Receives a slug that survived the subscription and user preference steps and returns what Laravel's dispatcher expects — a channel name or class — or `null` to skip it. Built-in mapping:

| Slug | Result |
|---|---|
| `mail` | `'mail'` when `$notifiable->email` is not empty, otherwise `null` |
| `database`, `broadcast` | the slug |
| anything else | `null` |

So a slug you don't map is silently dropped. Everything else in `via()` — opt-outs, user channels, subscription, the non-configurable fallback, `only()`/`except()` — applies to your channels without further code.

> [!WARNING]
> Don't copy `via()` into the host app. A copy freezes the resolution chain at the version you copied: fixes to opt-out handling or fallback rules in later releases never reach it. Override `mapChannel()` instead (available since 0.7.0).

For mail to on-demand recipients or models with `routeNotificationForMail()`, see [Sending — on-demand recipients](sending.md#on-demand-recipients).

## Message helpers

| Method | Returns |
|---|---|
| `getMessengerBody($notifiable)` | Body of the `messenger` slot, falling back to the `mail` slot, then `defaults.mail.body`; through `prepareText()` |
| `getMessengerButtons($notifiable)` | Link buttons, see [Messenger buttons](messenger-buttons.md) |
| `getMessengerButtonsColumns()` | Buttons per row |
| `resolveTemplate($slot)` | The `NotifyTemplate` row for any slot, or `null` |

Messengers have length limits and their own markup rules; trimming and stripping HTML per channel is up to your `to{Channel}()`.

## A custom mail view

The default `toMail()` puts the body into a single `line()`, where Laravel escapes HTML and joins line breaks. For HTML templates render your own view:

```php
public function toMail(mixed $notifiable): MailMessage
{
    $template = $this->resolveTemplate('mail');

    return (new MailMessage())
        ->subject($this->prepareText($template?->subject ?: $this->getSubjectDefault(), $notifiable))
        ->view('mails.plain', [
            'body' => $this->prepareText($template?->body ?: $this->getBodyDefault(), $notifiable),
        ]);
}
```

## database and broadcast

`toArray()` returns `['message' => strip_tags($this->getMessengerBody($notifiable))]` for both. Override `toArray()`, `toDatabase()` or `toBroadcast()` for a richer payload.
