# Artisan & publishing

## notify:make

```bash
php artisan notify:make OrderOrdered
php artisan notify:make Shop/OrderOrdered
```

Creates a `BaseNotify` subclass in `App\Notifications` (subdirectories via `/`). `Notify` is appended to the class name when missing; `{{ notifyKey }}` in the stub becomes the name without the suffix. The generated class is `final`, implements `ShouldQueue`, uses `Queueable`, takes `string $roleKey` in the constructor, and has empty `prepareText()` and `typeDefinition()` scaffolding.

The command has no options; it refuses to overwrite an existing class.

A `stubs/notify.stub` file in the project root replaces the package stub.

## Publishing

| Tag | Publishes |
|---|---|
| `notify-templates-config` | `config/notify-templates.php` |
| `notify-templates-migrations` | The migrations not yet present in `database/migrations`: `create_notifytemplates_tables`, `create_notify_logs_table`, `add_content_to_notify_logs_table` |
| `notify-templates-lang` | `lang/vendor/notify-templates/{en,uk}/log.php` |

```bash
php artisan vendor:publish --tag=notify-templates-migrations
```

> [!WARNING]
> On a fresh install all three migrations get the same timestamp and `add_content_to_notify_logs_table` runs first and fails. Delete it — see [Installation](../installation.md#install).

## Pruning the log

Not a package command — Laravel's `model:prune`, scheduled by you:

```php
Schedule::command('model:prune', ['--model' => [\Fomvasss\NotifyTemplates\Models\NotifyLog::class]])->daily();
```
