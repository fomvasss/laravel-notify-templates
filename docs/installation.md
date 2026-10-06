# Installation

## Requirements

- PHP ^8.2
- Laravel 10, 11, 12 or 13 (`illuminate/support`, `illuminate/database`, `illuminate/notifications`)
- PostgreSQL, MySQL 8.0.13+ or SQLite

The migration creates unique indexes over `COALESCE(...)` expressions (functional key parts). MySQL supports them from 8.0.13; MariaDB does not support them at all.

## Install

```bash
composer require fomvasss/laravel-notify-templates
```

The service provider and the `NotifyTemplates` facade alias are registered by package auto-discovery.

Publish and run the migrations:

```bash
php artisan vendor:publish --tag=notify-templates-migrations
php artisan migrate
```

> [!WARNING]
> On a fresh install the command publishes three migrations with the same timestamp, and Laravel runs them in file name order: `add_content_to_notify_logs_table` sorts before `create_notify_logs_table` and fails with "table notify_logs doesn't exist". On a fresh install `add_content_to_notify_logs_table` is not needed — delete it, or rename it so its timestamp is later than `create_notify_logs_table`. It exists only for installs that created `notify_logs` on 0.8.x.

Each migration is published only if a file with the same name suffix is not in `database/migrations` yet, so running the command again after an upgrade publishes only the new ones.

| Migration | Creates |
|---|---|
| `create_notifytemplates_tables` | `notify_templates`, `notify_role_subscriptions`, `notify_user_settings` |
| `create_notify_logs_table` | `notify_logs` — used only when the [delivery log](usage/delivery-log.md) is enabled |
| `add_content_to_notify_logs_table` | `notify_logs.subject`, `notify_logs.body` for 0.8.x installs; a no-op when the columns exist |

Publish the config if you need to change anything in it (optional):

```bash
php artisan vendor:publish --tag=notify-templates-config
```

Publish the status label translations if you want to change them (optional):

```bash
php artisan vendor:publish --tag=notify-templates-lang
```

## Bind a recipient resolver

The package does not know your roles and users. If notifications are sent per role from event listeners, implement `NotifyRoleResolverInterface` and bind it — see [Recipients & role subscriptions](usage/recipients.md). The package itself never calls the resolver, so nothing breaks without a binding until your own code asks for it.

```php
// AppServiceProvider::register()
$this->app->bind(
    \Fomvasss\NotifyTemplates\Contracts\NotifyRoleResolverInterface::class,
    \App\Services\AppNotifyRoleResolver::class,
);
```

## First notification

```bash
php artisan notify:make OrderOrdered
```

This creates `app/Notifications/OrderOrderedNotify.php`. Fill in `typeDefinition()`, then create an active subscription for a role — nothing is sent for a regular type without one:

```php
use Fomvasss\NotifyTemplates\Models\NotifyRoleSubscription;

NotifyRoleSubscription::create([
    'role_key' => 'client',
    'notify_key' => 'OrderOrdered',
    'is_active' => true,
    'channels' => ['mail'],
]);
```

Next: [Notify types](usage/notify-types.md) and [Sending notifications](usage/sending.md).
