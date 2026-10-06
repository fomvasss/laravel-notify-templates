# Laravel Notify Templates

[![License](https://img.shields.io/packagist/l/fomvasss/laravel-notify-templates.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-notify-templates)
[![Latest Stable Version](https://img.shields.io/packagist/v/fomvasss/laravel-notify-templates.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-notify-templates)
[![Total Downloads](https://img.shields.io/packagist/dt/fomvasss/laravel-notify-templates.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-notify-templates)

Шаблони сповіщень у базі даних для Laravel. Адміністратор редагує тему й текст кожного сповіщення окремо для каналу, ролі й тенанта; пакет підбирає потрібний шаблон, визначає канали доставки з підписок ролей і налаштувань користувача та веде журнал відправок — без прив'язки до конкретного пакета ролей.

[English](README.md)

Документація англійською — https://fomvasss.github.io/laravel-notify-templates/ (ті самі сторінки, що в [docs/](docs/index.md)).

- **Шаблони в БД** — на тип сповіщення × слот каналу × роль × тенант, з 8-рівневим fallback аж до значень за замовчуванням у коді
- **Реєстр типів** — типи сповіщень автоматично знаходяться в `app/Notifications`, з метаданими для адмінки
- **Підписки ролей** — які ролі отримують який тип, якими каналами, з якою затримкою
- **Налаштування користувача** — вимкнути тип або звузити його канали; обов'язкові типи для OTP-кодів
- **Один ланцюжок `via()`** для вбудованих і власних каналів (Telegram, SMS, …) через хук `mapChannel()`
- **Кнопки в месенджерах** — кнопки-посилання під повідомленнями Telegram, мультимовні
- **Журнал відправок** — `notify_logs` зі статусами провайдера `sent` → `delivered` → `read`

## Вимоги

- PHP ^8.2
- Laravel 10 – 13
- PostgreSQL, MySQL 8.0.13+ або SQLite (не MariaDB)

## Встановлення

```bash
composer require fomvasss/laravel-notify-templates

php artisan vendor:publish --tag=notify-templates-migrations
php artisan migrate
```

> **Нове встановлення:** перед `migrate` видаліть опубліковану `*_add_content_to_notify_logs_table.php` — вона отримує ту саму мітку часу, що й `create_notify_logs_table`, запускається першою і падає. Вона потрібна лише для оновлення з 0.8.x.

## Швидкий старт

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
            'name' => 'Замовлення оформлено',
            'group' => 'order',
            'defaults' => [
                'mail' => ['subject' => 'Замовлення оформлено', 'body' => 'Ваше замовлення [order:number] прийнято.'],
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

## Документація

- [Installation](docs/installation.md) · [Configuration](docs/configuration.md)
- [Notify types](docs/usage/notify-types.md) · [Sending](docs/usage/sending.md) · [Recipients & role subscriptions](docs/usage/recipients.md) · [Templates](docs/usage/templates.md)
- [Channel resolution](docs/usage/channels.md) · [User preferences](docs/usage/user-preferences.md) · [Custom channels](docs/usage/custom-channels.md) · [Messenger buttons](docs/usage/messenger-buttons.md)
- [Delivery log](docs/usage/delivery-log.md) · [Multi-tenancy](docs/usage/multi-tenancy.md) · [Multilingual templates](docs/usage/translations.md) · [Queues & Octane](docs/usage/queues-octane.md)
- Довідник: [Facade](docs/reference/facade.md) · [BaseNotify](docs/reference/base-notify.md) · [typeDefinition()](docs/reference/type-definition.md) · [Tables & models](docs/reference/models.md) · [Contracts](docs/reference/contracts.md) · [Artisan & publishing](docs/reference/commands.md)
- [Upgrading](docs/upgrading.md) · [Changelog](CHANGELOG.md)

## Ліцензія

MIT — дивись [LICENSE](LICENSE.md).
