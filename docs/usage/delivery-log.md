# Delivery log

An opt-in journal of sent notifications: one `notify_logs` row per notification × channel × recipient, with the address, the subject and body actually sent, the provider's message id and a delivery status. Only notifications extending `BaseNotify` are logged.

## Enabling

```php
// config/notify-templates.php
'log' => [
    'enabled' => true,
    // ...
],
```

The `notify_logs` table comes from the `create_notify_logs_table` migration (see [Installation](../installation.md)). The setting is read when the application boots; with `log.enabled = false` no listener is registered at all.

## What gets written

The package listens to Laravel's notification events:

| Event | Effect |
|---|---|
| `NotificationSending` | Creates the row as `pending`. A send that dies without another event (killed worker, timeout) stays `pending` as a trace |
| `NotificationSent` | `sent`, plus `external_id` and `subject`/`body` from the channel's response |
| `NotificationFailed` | `failed`, with the error message and the event data in `payload` |

- A queue retry of the same notification (same notification id, channel and notifiable) reuses the row: status back to `pending`, error cleared, `attempts` + 1.
- A channel that catches its own exception dispatches `NotificationFailed` and returns normally, after which Laravel still fires `NotificationSent`. The `failed` status is kept.
- `route` is the address the channel delivers to — email, chat id, phone — from `routeNotificationFor()`, cut to 255 characters.
- `notifiable_type`/`notifiable_id` are `null` for on-demand recipients (`Notification::route()`).
- `tenant_id` is the notification's tenant, or `config('notify-templates.tenant_id')`.

## Subject and body

`subject` and `body` hold what was sent, tokens already substituted. They are extracted from the channel's response by a content resolver per channel:

| Resolver | Channel | Extracts |
|---|---|---|
| `MailContentResolver` | `mail` | Subject, and the HTML body (text body when there is no HTML) |
| `TelegramContentResolver` | `telegram` | Message text or caption; parts of a chunked message joined; url buttons appended as `[text] url` |

The subject is stored whenever the resolver returns one (cut to 255 characters). The body is skipped:

- for every type, with `log.store_body = false`;
- for one type, with `'log_body' => false` in `typeDefinition()` — use it for login codes, generated passwords and anything that must not be readable in the log.

A channel without a content resolver leaves both columns empty.

## Delivery status

`pending → sent → delivered → read`, plus `failed`.

`sent` means the provider accepted the message. `delivered` and `read` exist only where the provider reports them — WhatsApp, Viber, SMS gateways with delivery reports, ESP webhooks. For mail over plain SMTP or a Telegram bot, `sent` is final.

Feed provider reports (webhook or status poll) into:

```php
NotifyTemplates::updateDelivery($channel, $externalId, 'delivered', $rawPayload);
```

- `$channel` is the channel as `via()` returned it: `'mail'`, `'telegram'` or a channel class name.
- The newest row (by `created_at`) with this channel and `external_id` is updated: `status`, `payload` (`null` when empty) and `status_updated_at`.
- The status only moves forward, because reports arrive out of order (`delivered` after `read`). Ranks: `pending` 0, `sent` 1, `delivered` 2, `failed` 2, `read` 3. So `failed` is accepted over `sent` but not over `delivered`, and `read` is accepted even over `failed`.
- Returns `false` when no row matches or the report is stale.
- Throws `InvalidArgumentException` for `pending` or a status that doesn't exist.

## External ids

The id that reports are matched by comes from an external id resolver per channel:

| Resolver | Channel | Id |
|---|---|---|
| `MailMessageIdResolver` | `mail` | `Message-ID` of the sent mail |
| `TelegramMessageIdResolver` | `telegram` | Bot API `message_id` (the first part of a chunked message) |

The Telegram resolvers read the response of [laravel-notification-channels/telegram](https://github.com/laravel-notification-channels/telegram). For another channel, implement the interface and bind it by the channel name `via()` returns:

```php
use Fomvasss\NotifyTemplates\Contracts\ExternalIdResolverInterface;

class TurboSmsIdResolver implements ExternalIdResolverInterface
{
    public function resolve(mixed $response): ?string
    {
        return $response['response_result'][0]['message_id'] ?? null;
    }
}
```

```php
'external_id_resolvers' => [
    'mail' => \Fomvasss\NotifyTemplates\Resolvers\MailMessageIdResolver::class,
    'telegram' => \Fomvasss\NotifyTemplates\Resolvers\TelegramMessageIdResolver::class,
    \App\Channels\TurboSmsChannel::class => \App\Notifications\TurboSmsIdResolver::class,
],
```

`$response` is whatever the channel's `send()` returned (`NotificationSent::$response`). Resolvers are created through the container. Content resolvers implement `ContentResolverInterface` and return `['subject' => ?string, 'body' => ?string]` — see [Contracts](../reference/contracts.md).

## Status labels

```php
NotifyLog::statusLabels(); // ['pending' => 'Pending', 'sent' => 'Sent', ...] in lifecycle order
$log->getStatusLabel();    // label of this row's status
```

`en` and `uk` are included. To change the wording or add a locale, publish and edit `lang/vendor/notify-templates/{locale}/log.php`:

```bash
php artisan vendor:publish --tag=notify-templates-lang
```

## Pruning

`NotifyLog` is `MassPrunable`: rows with `created_at` older than `log.retention_days` are deleted by `model:prune`, which you schedule:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune', ['--model' => [\Fomvasss\NotifyTemplates\Models\NotifyLog::class]])->daily();
```

Since the model lives in the package, `model:prune` without `--model` doesn't find it.

## Querying

```php
use Fomvasss\NotifyTemplates\Models\NotifyLog;

NotifyLog::query()
    ->where('notifiable_type', $user->getMorphClass())
    ->where('notifiable_id', $user->getKey())
    ->latest()
    ->paginate();
```

`notifiable()` is a morph-to relation. Indexes exist on `notification_id`, `(channel, external_id)`, `(notifiable_type, notifiable_id)` and `created_at`.
