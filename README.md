# Laravel Notify Templates

[![License](https://img.shields.io/packagist/l/fomvasss/laravel-notify-templates.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-notify-templates)
[![Latest Stable Version](https://img.shields.io/packagist/v/fomvasss/laravel-notify-templates.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-notify-templates)
[![Total Downloads](https://img.shields.io/packagist/dt/fomvasss/laravel-notify-templates.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-notify-templates)

Database-driven notification templates for Laravel. Admins edit the subject and body of every notification per channel, role and tenant; the package picks the right template, resolves the delivery channels from role subscriptions and user preferences, and logs what was delivered — without tying you to a role package.

[Українською](README.uk.md)

- **Templates in the database** — per notify type × channel slot × role × tenant, with an 8-level fallback down to defaults in code
- **Type registry** — notify types auto-discovered from `app/Notifications`, with metadata for an admin UI
- **Role subscriptions** — which roles get which type, through which channels, with what delay
- **User preferences** — turn a type off or narrow its channels per user; non-optional types for OTP codes
- **One `via()` chain** for built-in and custom channels (Telegram, SMS, …) through a `mapChannel()` hook
- **Messenger buttons** — link buttons under Telegram messages, multilingual
- **Delivery log** — `notify_logs` with provider statuses `sent` → `delivered` → `read`

## Requirements

- PHP ^8.2
- Laravel 10 – 13
- PostgreSQL, MySQL 8.0.13+ or SQLite (not MariaDB)

## Installation

```bash
composer require fomvasss/laravel-notify-templates

php artisan vendor:publish --tag=notify-templates-migrations
php artisan migrate
```

> **Fresh install:** delete the published `*_add_content_to_notify_logs_table.php` before `migrate` — it gets the same timestamp as `create_notify_logs_table`, runs first and fails. It is only for upgrades from 0.8.x.

## Quick start

```bash
php artisan notify:make OrderOrdered
```

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
use Fomvasss\NotifyTemplates\Models\NotifyRoleSubscription;

NotifyRoleSubscription::create(['role_key' => 'client', 'notify_key' => 'OrderOrdered', 'channels' => ['mail']]);

$user->notify(new OrderOrderedNotify($order, 'client'));
```

## Documentation

Online: **https://fomvasss.github.io/laravel-notify-templates/** — the same pages as in [docs/](docs/index.md).

- [Installation](docs/installation.md) · [Configuration](docs/configuration.md)
- [Notify types](docs/usage/notify-types.md) · [Sending](docs/usage/sending.md) · [Recipients & role subscriptions](docs/usage/recipients.md) · [Templates](docs/usage/templates.md)
- [Channel resolution](docs/usage/channels.md) · [User preferences](docs/usage/user-preferences.md) · [Custom channels](docs/usage/custom-channels.md) · [Messenger buttons](docs/usage/messenger-buttons.md)
- [Delivery log](docs/usage/delivery-log.md) · [Multi-tenancy](docs/usage/multi-tenancy.md) · [Multilingual templates](docs/usage/translations.md) · [Queues & Octane](docs/usage/queues-octane.md)
- Reference: [Facade](docs/reference/facade.md) · [BaseNotify](docs/reference/base-notify.md) · [typeDefinition()](docs/reference/type-definition.md) · [Tables & models](docs/reference/models.md) · [Contracts](docs/reference/contracts.md) · [Artisan & publishing](docs/reference/commands.md)
- [Upgrading](docs/upgrading.md) · [Changelog](CHANGELOG.md)

## License

MIT — see [LICENSE](LICENSE.md).
