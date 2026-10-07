# Notify types

A notify type is one kind of notification — "order placed", "password reset", "login code". Its key ties together the subscriptions, templates and user settings stored in the database. The type registry holds the metadata of every type for an admin UI: name, group, tokens, defaults.

## The Notify class

```bash
php artisan notify:make OrderOrdered
# app/Notifications/OrderOrderedNotify.php

php artisan notify:make Shop/OrderOrdered
# app/Notifications/Shop/OrderOrderedNotify.php
```

The `Notify` suffix is appended when missing. The generated class is queued (`ShouldQueue` + `Queueable`), takes `$roleKey` in the constructor and has a pre-filled `typeDefinition()`:

```php
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
            'weight' => 20,
            'desc' => 'Sent when the customer places an order',
            'settings' => ['delay'],
            'tokens' => [
                ['key' => '[order:number]', 'name' => 'Order number'],
                ['key' => '[user:name]', 'name' => 'Customer name'],
            ],
            'defaults' => [
                'mail' => ['subject' => 'Order placed', 'body' => 'Your order [order:number] has been received.'],
                'messenger' => ['body' => 'New order [order:number]'],
            ],
        ];
    }
}
```

All keys are listed in [typeDefinition() keys](../reference/type-definition.md).

> [!WARNING]
> The type key that the class uses at send time is `notifyKey()` — the class name without the trailing `Notify` (`OrderOrderedNotify` → `OrderOrdered`). The registry stores the type under `typeDefinition()['key']`. Keep the two equal: since 0.12.4 discovery throws a `LogicException` naming the class when they differ, and a discovered class may leave `key` out to take `notifyKey()`. If the class name doesn't follow the convention, override `notifyKey()`. Types registered by hand (`registerType()`, config) are not checked.

```php
public static function notifyKey(): string
{
    return 'OrderOrdered';
}
```

> [!WARNING]
> `$roleKey` must be set — by the constructor or otherwise. `via()` and template resolution read it, and an uninitialized typed property throws an `Error`.

## Auto-discovery

On every boot the service provider scans the directories in `config('notify-templates.discover')` (default `app_path('Notifications')`) recursively and registers each class that extends `BaseNotify` and returns a non-empty `key` from `typeDefinition()`. Set `discover` to `[]` to turn it off, or scan another directory yourself:

```php
NotifyTemplates::discoverIn(app_path('Domain/Notifications'));
```

How a file is recognized: the scanner reads the `namespace` line and the first line starting with `class` or `final class`, then checks the class with `class_exists()` and `is_subclass_of()`. Abstract base classes are skipped, which is what you want for your app's own base notification.

> [!NOTE]
> Discovery reads and autoloads every PHP file in the directories on each boot (each request under PHP-FPM, once per worker under Octane or a queue worker). With a large `app/Notifications`, consider registering types explicitly and setting `discover` to `[]`.

## Manual registration

For types that don't map to a class, or are generated from data, register them in `AppServiceProvider::boot()`:

```php
use Fomvasss\NotifyTemplates\Facades\NotifyTemplates;

NotifyTemplates::registerTypes([
    [
        'key' => 'UserCreated',
        'name' => 'User created',
        'group' => 'user',
        'weight' => 10,
        'settings' => ['delay'],
        'tokens' => [
            ['key' => '[user:name]', 'name' => 'Name'],
            ['key' => '[user:email]', 'name' => 'Email'],
        ],
    ],
]);

foreach (Order::statusesList() as $status) {
    NotifyTemplates::registerType([
        'key' => 'OrderStatus'.ucfirst($status['key']),
        'name' => 'Status: '.$status['name'],
        'group' => 'order',
    ]);
}
```

Or statically in the config:

```php
'types' => [
    ['key' => 'UserCreated', 'name' => 'User created', 'group' => 'user'],
],
```

`registerType()` throws `InvalidArgumentException` when `key` is empty. Registering an existing key replaces it. Config types are registered after discovery.

> [!WARNING]
> Register types only while the application boots. The manager is a singleton; under Octane a type registered during a request stays registered for every later request of that worker.

## Reading the registry

```php
NotifyTemplates::getTypes();                    // ['OrderOrdered' => [...], ...]
NotifyTemplates::getTypes('order');             // only group "order"
NotifyTemplates::getType('OrderOrdered');       // the definition, or null
NotifyTemplates::getTypeChannels('OrderOrdered'); // its 'channels', or config('notify-templates.channels')
NotifyTemplates::isUserConfigurable('OrderOrdered');
```

`getTypes()` returns the types in registration order; sorting by `weight` within a group is up to your UI.

## Metadata for the admin UI

`name`, `group`, `weight`, `desc`, `tokens`, `channels` and `settings` are not used when sending — they exist for the admin screens you build. `defaults` is used: `defaults.mail.subject` and `defaults.mail.body` are the fallback when no template row matches (see [Templates](templates.md)).

### settings

`settings` lists option keys that the admin can edit per role subscription; values are stored in `notify_role_subscriptions.options`. The package reads one of them itself, `delay` (minutes):

```php
'settings' => ['delay'],
// notify_role_subscriptions.options = {"delay": 5}
NotifyTemplates::resolveDelay('OrderOrdered', 'client'); // 300 (seconds)
```

Any other key is yours — read it with `$subscription->getOption('key')`. A controller can save all declared keys without knowing them:

```php
$settings = NotifyTemplates::getType($notifyKey)['settings'] ?? [];

if ($settings) {
    $sub = NotifyRoleSubscription::firstOrNew(
        ['notify_key' => $notifyKey, 'role_key' => $roleKey, 'tenant_id' => null],
        ['is_active' => false, 'personal_only' => false, 'channels' => []],
    );
    $sub->options = array_merge($sub->options ?? [], $request->only($settings));
    $sub->save();
}
```

### Custom keys

`registerType()` stores the whole array as it is. Any key beyond the documented ones comes back from `getType()` untouched, so project-specific rules need no fork. The package ignores such keys; your admin UI enforces them:

```php
public static function typeDefinition(): array
{
    return [
        'key' => 'UserOtp',
        // ...
        'user_configurable' => false,
        // only this role may have a subscription/template: the code always goes to the user logging in
        'allowed_roles' => ['client'],
        // sent directly with $user->notify(), not through a role resolver — the admin UI locks the toggle
        'always_sent' => true,
    ];
}
```

```php
$allowed = NotifyTemplates::getType($key)['allowed_roles'] ?? null;
$roleAllowed = $allowed === null || in_array($role, $allowed, true);

abort_if(NotifyTemplates::getType($key)['always_sent'] ?? false, 422, 'This type is always sent');
```

Why `always_sent` makes sense: a type with `'user_configurable' => false` sent directly falls back to `default_channels` even when its subscription is inactive, so disabling it in the UI would be a lie. A non-configurable type that still goes through a role resolver is disabled for real by an inactive subscription — the resolver returns no recipients for that role.

## Customizing the stub

`notify:make` uses `stubs/notify.stub` from your project root when it exists:

```bash
mkdir -p stubs
cp vendor/fomvasss/laravel-notify-templates/src/Console/stubs/notify.stub stubs/notify.stub
```

The stub supports `{{ namespace }}`, `{{ class }}` and `{{ notifyKey }}`.
