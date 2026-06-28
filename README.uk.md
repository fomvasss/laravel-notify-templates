# laravel-notify-templates

[![License](https://img.shields.io/packagist/l/fomvasss/laravel-notify-templates.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-notify-templates)
[![Latest Stable Version](https://img.shields.io/packagist/v/fomvasss/laravel-notify-templates.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-notify-templates)
[![Total Downloads](https://img.shields.io/packagist/dt/fomvasss/laravel-notify-templates.svg?style=for-the-badge)](https://packagist.org/packages/fomvasss/laravel-notify-templates)

DB-шаблони сповіщень для Laravel. Управляє шаблонами, підписками ролей/користувачів, розв'язанням каналів і затримкою — без прив'язки до конкретного пакету ролей.

> English: [README.md](README.md)

---

## Концепції

- **`notify_templates`** — subject/body по типу сповіщення + слот каналу + роль + тенант, з ланцюжком fallback
- **`notify_role_subscriptions`** — які типи активні для якої ролі (канали, затримка, personal_only)
- **`BaseNotify`** — абстрактний базовий клас; розв'язує шаблони та канали; конкретні класи живуть у додатку
- **`NotifyTemplatesManager`** — реєстр типів + методи розв'язання, доступний через фасад `NotifyTemplates`

---

## Встановлення

```bash
composer require fomvasss/laravel-notify-templates
```

Опублікувати та запустити міграції:

```bash
php artisan vendor:publish --tag=notify-templates-migrations
php artisan migrate
```

Опублікувати конфіг (опціонально):

```bash
php artisan vendor:publish --tag=notify-templates-config
```

---

## Конфігурація

`config/notify-templates.php`:

```php
return [
    'tables' => [
        'notify_templates'          => 'notify_templates',
        'notify_role_subscriptions' => 'notify_role_subscriptions',
    ],

    // Усі канали доставки що реалізовані в проекті.
    // Використовується як список у UI та як fallback коли typeDefinition()['channels'] порожній.
    'channels' => ['mail', 'telegram', 'sms', 'database', 'broadcast'],

    // Канали за замовчуванням — коли підписка не має налаштованих каналів
    // або via() нічого не розв'язав
    'default_channels' => ['mail'],

    // null — однотенантний. Або callable що повертає рядок ID тенанта
    'tenant_id' => null,

    // Директорії для авто-виявлення підкласів BaseNotify при завантаженні
    'discover' => [
        app_path('Notifications'),
    ],

    // Статична реєстрація типів через конфіг
    'types' => [],

    // Перевизначення моделей (наприклад, для підтримки перекладів)
    'models' => [
        'notify_template' => \Fomvasss\NotifyTemplates\Models\NotifyTemplate::class,
    ],
];
```

---

## Реєстрація типів

### Авто-виявлення (рекомендовано)

Пакет сканує `app/Notifications` при кожному завантаженні — рекурсивно, включаючи підпапки. Знаходить усі класи що розширюють `BaseNotify` і повертають непорожній `typeDefinition()`.

```php
// config/notify-templates.php
'discover' => [
    app_path('Notifications'),
],
```

Щоб вимкнути — встановіть `[]`.

### Ручна реєстрація

```php
// AppServiceProvider::boot()
NotifyTemplates::registerTypes([
    [
        'key'      => 'OrderOrdered',
        'name'     => 'Замовлення оформлено',
        'group'    => 'order',
        'weight'   => 10,
        'channels' => ['mail', 'sms'], // порожньо = усі канали з конфігу
        'settings' => ['delay'],
        'tokens'   => [
            ['key' => '[order:number]', 'name' => 'Номер замовлення'],
        ],
        'defaults' => [
            'mail'      => ['subject' => 'Замовлення #[order:number]', 'body' => 'Ваше замовлення оформлено.'],
            'messenger' => ['body' => 'Замовлення #[order:number] оформлено.'],
        ],
    ],
]);
```

### Поля typeDefinition()

| Поле | Тип | Опис |
|---|---|---|
| `key` | string | Унікальний ідентифікатор, напр. `'OrderOrdered'` |
| `name` | string | Назва для UI |
| `group` | string | Група для таблиць UI, напр. `'order'` |
| `weight` | int | Вага сортування в межах групи |
| `channels` | array | Канали цього типу. Порожньо — fallback на `config('notify-templates.channels')` |
| `settings` | array | Ключі налаштувань що редагуються в адмінці; зберігаються в `options` підписки |
| `tokens` | array | Підказки токенів для редактора шаблону |
| `defaults` | array | Дефолтні subject/body по слоту каналу |
| `system` | bool | Системне сповіщення (OTP, скидання паролю тощо) — завжди активне. UI повинен приховувати toggle вкл/викл для таких типів і не блокувати відправку на основі підписки |

Єдиний ключ що пакет читає нативно в `settings` — `delay` (затримка в хвилинах):

```php
'settings' => ['delay']
// options = {"delay": 5} → resolveDelay() поверне 300 секунд
```

---

## Конкретні класи сповіщень

```bash
php artisan notify:make OrderOrderedNotify
```

```php
final class OrderOrderedNotify extends BaseNotify implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $roleKey,
        protected Order $order,
    ) {}

    protected function prepareText(string $text, mixed $notifiable): string
    {
        return str_replace('[order:number]', $this->order->number, $text);
    }

    public static function typeDefinition(): array
    {
        return [
            'key'      => 'OrderOrdered',
            'name'     => 'Замовлення оформлено',
            'group'    => 'order',
            'weight'   => 10,
            'channels' => [],
            'settings' => ['delay'],
            'tokens'   => [
                ['key' => '[order:number]', 'name' => 'Номер замовлення'],
            ],
            'defaults' => [
                'mail'      => ['subject' => 'Замовлення #[order:number]', 'body' => ''],
                'messenger' => ['body' => ''],
            ],
        ];
    }
}
```

Хуки що можна перевизначати:
- `prepareText(string $text, mixed $notifiable): string` — заміна токенів/шорткодів
- `via(mixed $notifiable): array` — розширення списку каналів (викликайте `parent::via()`)
- `resolveTemplate(string $channel): ?NotifyTemplate` — доступ до розв'язаного шаблону

### only() / except()

```php
// примусово лише mail
$user->notify((new OrderOrderedNotify('client', $order))->only(['mail']));

// усі розв'язані канали крім sms
$user->notify((new OrderOrderedNotify('client', $order))->except(['sms']));
```

---

## Слухачі та відправка

```php
use Fomvasss\NotifyTemplates\Contracts\NotifyRoleResolverInterface;
use Fomvasss\NotifyTemplates\Facades\NotifyTemplates;
use Illuminate\Support\Facades\Notification;

class OrderOrderedListener
{
    public function __construct(private NotifyRoleResolverInterface $resolver) {}

    public function handle(OrderOrdered $event): void
    {
        $notifyKey = 'OrderOrdered';

        foreach (NotifyTemplates::getTypes() as $type) {
            $roleKey = $type['key']; // або власна логіка визначення ролі
            $users   = $this->resolver->resolve($roleKey, $notifyKey, $event->order);
            $delay   = NotifyTemplates::resolveDelay($notifyKey, $roleKey);

            Notification::send(
                $users,
                (new OrderOrderedNotify($roleKey, $event->order))->delay($delay)
            );
        }
    }
}
```

---

## Модель User

Визначте метод `getNotifyChannels()` для персональних налаштувань каналів:

```php
class User extends Authenticatable
{
    public function getNotifyChannels(): array
    {
        return $this->notify_channels ?? []; // з колонки в БД
    }
}
```

Результат **перетинається** з каналами підписки ролі — юзер може відписатись від каналу, але не додати новий понад те що дозволяє роль. Якщо метод відсутній або повертає `[]` — використовуються усі канали підписки.

---

## NotifyRoleResolverInterface

Визначає яких юзерів отримують сповіщення певного типу:

```php
use Fomvasss\NotifyTemplates\Contracts\NotifyRoleResolverInterface;

class NotifyRoleResolver implements NotifyRoleResolverInterface
{
    public function resolve(string $roleKey, string $notifyKey, mixed $context = null): iterable
    {
        return User::role($roleKey)->get();
    }
}
```

```php
// AppServiceProvider::register()
$this->app->bind(NotifyRoleResolverInterface::class, NotifyRoleResolver::class);
```

---

## Ланцюжок fallback шаблонів (8 рівнів)

Для кожного сповіщення пакет шукає найточніший шаблон у БД:

```
(channel + role + tenant)
(channel + role)
(channel + tenant)
(channel)
(null + role + tenant)
(null + role)
(null + tenant)
(null)
```

Специфічніший завжди виграє. Якщо шаблон не знайдено — використовуються `defaults` з `typeDefinition()`.

---

## personal_only

Прапор `personal_only` на `notify_role_subscriptions` — надсилати лише конкретній людині з контексту події, а не всім юзерам з цією роллю. Логіка реалізується на стороні додатку в `NotifyRoleResolverInterface::resolve()`.

---

## API фасаду

```php
NotifyTemplates::registerType(array $type): void
NotifyTemplates::registerTypes(array $types): void
NotifyTemplates::discoverIn(string $path): void
NotifyTemplates::getTypes(?string $group = null): array
NotifyTemplates::getType(string $key): ?array
NotifyTemplates::getTypeChannels(string $notifyKey): array

NotifyTemplates::resolveTemplate(string $notifyKey, string $channel, ?string $roleKey, ?string $tenantId): ?NotifyTemplate
NotifyTemplates::resolveChannels(string $notifyKey, string $roleKey, ?string $tenantId, array $userChannels = []): array
NotifyTemplates::resolveDelay(string $notifyKey, string $roleKey, ?string $tenantId): int
```

---

## Порядок вирішення каналів

Кожне сповіщення проходить фіксований ланцюг всередині `via()`. Кожен крок може лише звужувати канали — розширити те, що відфільтрував попередній крок, неможливо.

```
1. typeDefinition()['channels']
       канали, які підтримує цей тип сповіщення
       порожній → fallback до config('notify-templates.channels')
       ↓
2. notify_role_subscriptions.channels   (задається в адмін-UI)
       які канали увімкнені для пари роль+тип сповіщення
       порожній → fallback до config('notify-templates.default_channels')
       ↓
3. getNotifyChannels()   (модель User)
       канальні преференції конкретного юзера
       непорожній → перетинається з кроком 2 (юзер може відписатися, але не додати)
       порожній / метод відсутній → всі канали з кроку 2 проходять далі
       ↓
4. routeNotificationFor*()
       фізична перевірка: чи є у юзера email / telegram id / тощо?
       канал мовчки відкидається, якщо маршрут порожній
       якщо нічого не вижило → fallback до config('notify-templates.default_channels')
       ↓
5. only() / except()   (виклик у коді)
       застосовується останнім, завжди має пріоритет
```

**Практичні приклади:**

| Сценарій | Результат |
|---|---|
| Немає підписок у БД, нічого не налаштовано | `mail` (з `default_channels`) |
| Підписка активна, `channels = []` у БД | `mail` (з `default_channels`) |
| Підписка `['mail','telegram']`, у юзера нема telegram id | тільки `mail` |
| Підписка `['mail','telegram']`, `getNotifyChannels()` повертає `['mail']` | тільки `mail` |
| `->only(['telegram'])` на місці виклику | тільки `telegram`, незалежно від підписки |

`config('notify-templates.channels')` — це лише **список для UI**: він визначає чекбокси на формі редагування та є fallback для `getTypeChannels()`. На відправку безпосередньо не впливає.

---

## Журнал змін

Дивіться [CHANGELOG.md](CHANGELOG.md).

## Ліцензія

MIT — [LICENSE.md](LICENSE.md).
