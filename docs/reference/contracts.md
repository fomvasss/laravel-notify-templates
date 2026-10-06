# Contracts & resolvers

Namespace `Fomvasss\NotifyTemplates\Contracts`.

| Interface | Method | Implemented by | Bound |
|---|---|---|---|
| `NotifyRoleResolverInterface` | `resolveUsersForNotify(string $notifyKey, mixed $context = null): array` | your app | by you in the container; the package never calls it |
| `ExternalIdResolverInterface` | `resolve(mixed $response): ?string` | built-in + your own | `config('notify-templates.log.external_id_resolvers')` |
| `ContentResolverInterface` | `resolve(mixed $response): array` | built-in + your own | `config('notify-templates.log.content_resolvers')` |

## NotifyRoleResolverInterface

Returns notifiables grouped by role key: `['client' => Collection, 'admin' => Collection]`. `$context` is what the caller passes — usually the domain model the notification is about. See [Recipients & role subscriptions](../usage/recipients.md).

## ExternalIdResolverInterface

Receives what the channel's `send()` returned (`NotificationSent::$response`) and returns the provider's message id, or `null`. The id is stored in `notify_logs.external_id` and matched by `NotifyTemplates::updateDelivery()`.

## ContentResolverInterface

Receives the same response and returns `['subject' => ?string, 'body' => ?string]`; either key may be missing. Return `[]` when the response isn't recognized.

## Built-in resolvers

Namespace `Fomvasss\NotifyTemplates\Resolvers`.

| Class | Interface | Reads |
|---|---|---|
| `MailMessageIdResolver` | external id | `Illuminate\Mail\SentMessage::getMessageId()` |
| `MailContentResolver` | content | Subject and HTML body (text body as fallback) of the sent Symfony `Email` |
| `TelegramMessageIdResolver` | external id | `result.message_id` of the Bot API response; the first part of a chunked message |
| `TelegramContentResolver` | content | `result.text` or `result.caption` of each part, joined by newlines; inline url buttons appended as `[text] url` |

The Telegram resolvers expect the decoded Bot API response as returned by `laravel-notification-channels/telegram`: `{ok, result: {...}}`, or a list of those for a chunked message. Anything else gives `null` / `[]`.

Resolvers are resolved through the container, so they can have constructor dependencies.
