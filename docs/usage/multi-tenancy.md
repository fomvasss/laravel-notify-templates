# Multi-tenancy

Templates and role subscriptions have a nullable `tenant_id` (a string up to 100 characters). `null` rows are global; a tenant's own row wins over the global one.

| Table | Lookup with a tenant |
|---|---|
| `notify_templates` | Tenant row preferred, global row as fallback, after slot and role — see [fallback chain](templates.md#fallback-chain) |
| `notify_role_subscriptions` | The tenant's row if it exists, otherwise the global row |

Without a tenant only global rows are considered.

## Where the tenant comes from

1. `$this->tenantId` of the notification, when set:

   ```php
   public function __construct(protected Order $order, protected string $roleKey)
   {
       $this->tenantId = (string) $order->shop_id;
   }
   ```

2. Otherwise `config('notify-templates.tenant_id')` — `null`, a string or integer, or a callable:

   ```php
   'tenant_id' => [\App\Support\CurrentShop::class, 'id'],
   ```

The same fallback applies to every manager method that takes `?string $tenantId` (`resolveTemplate()`, `resolveChannels()`, `resolveDelay()`, `resolveButtons()`, `resolveButtonsColumns()`) and to the delivery log.

> [!WARNING]
> A callable runs at send time. In a queued notification that is the queue worker, where request-bound state (the current domain, the logged-in user's shop) is gone. Set `$this->tenantId` in the constructor — it is serialized with the notification — or make sure the callable works in a worker.

> [!NOTE]
> Passing `null` means "not given" and is replaced by the config value. Pass `''` (or set `$this->tenantId = ''`) to resolve only global rows (since 0.12.6).

The callable may return a string, an integer (cast to string) or `null`. A closure in the config breaks `config:cache`; use an array callable or a `'Class::method'` string. Any other string is a tenant id, never called — before 0.12.6 a tenant id that was also a PHP function name (`date`, `max`) got called.

## In your own queries

Model scopes and `resolve()` methods don't apply the config fallback. Resolve the tenant explicitly:

```php
$tenantId = NotifyTemplates::resolveTenantId(null);

NotifyRoleSubscription::query()->active()->forNotify($key)->forTenant($tenantId)->get();
```

## Uniqueness

The unique indexes include `tenant_id` (through `COALESCE(tenant_id, '')`), so each tenant can have its own template per slot and role and its own subscription per role, next to the global ones.
