# Laravel Notify Templates

Database-driven notification templates for Laravel. Admins edit the subject and body of every notification per channel, role and tenant; the package picks the right template, decides which channels a notification goes through and when, and logs what was actually delivered. It is not tied to any role package — who receives a notification is decided by your own resolver.

Every notification type is a class extending `BaseNotify`. The package takes care of everything around it:

- **Templates in the database** — subject/body per notify type × channel slot × role × tenant, with an 8-level fallback chain down to defaults in code
- **Type registry** — notify types auto-discovered from `app/Notifications`, with names, groups, tokens and defaults for an admin UI
- **Role subscriptions** — which roles get which type, through which channels, with what delay
- **User preferences** — a notifiable can turn a type off or narrow it to some channels; OTP-like types can be made non-optional
- **Channel resolution** — one `via()` chain for built-in and custom channels (Telegram, SMS, …) through a single `mapChannel()` hook
- **Messenger buttons** — link buttons under Telegram messages, multilingual, overridable per template
- **Delivery log** — what was sent to whom, with provider delivery statuses (`sent` → `delivered` → `read`)

## Quick example

```php
use Fomvasss\NotifyTemplates\Notifications\BaseNotify;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

final class OrderOrderedNotify extends BaseNotify implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Order $order, protected string $roleKey) {}

    public static function typeDefinition(): array
    {
        return [
            'key' => 'OrderOrdered',
            'name' => 'Order placed',
            'group' => 'order',
            'tokens' => [['key' => '[order:number]', 'name' => 'Order number']],
            'defaults' => [
                'mail' => ['subject' => 'Order placed', 'body' => 'Your order [order:number] has been received.'],
            ],
        ];
    }

    protected function prepareText(string $text, mixed $notifiable): string
    {
        return str_replace('[order:number]', $this->order->number, $text);
    }
}
```

```php
$user->notify(new OrderOrderedNotify($order, 'client'));
```

The mail goes out only if an active `client` subscription for `OrderOrdered` exists, with the subject and body from `notify_templates` (or the defaults above when there is no row).

## Contents

Getting started

1. [Installation](installation.md)
2. [Configuration](configuration.md)

Usage

3. [Notify types](usage/notify-types.md)
4. [Sending notifications](usage/sending.md)
5. [Recipients & role subscriptions](usage/recipients.md)
6. [Templates](usage/templates.md)
7. [Channel resolution](usage/channels.md)
8. [User preferences](usage/user-preferences.md)
9. [Custom channels](usage/custom-channels.md)
10. [Messenger buttons](usage/messenger-buttons.md)
11. [Delivery log](usage/delivery-log.md)
12. [Multi-tenancy](usage/multi-tenancy.md)
13. [Multilingual templates](usage/translations.md)
14. [Queues & Octane](usage/queues-octane.md)

Reference

15. [NotifyTemplates facade](reference/facade.md)
16. [BaseNotify](reference/base-notify.md)
17. [typeDefinition() keys](reference/type-definition.md)
18. [Tables & models](reference/models.md)
19. [Contracts & resolvers](reference/contracts.md)
20. [Artisan & publishing](reference/commands.md)

[Upgrading](upgrading.md)
