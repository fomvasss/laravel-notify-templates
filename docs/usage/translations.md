# Multilingual templates

The package stores one subject and body per template row. To translate them, swap the `NotifyTemplate` model for one with [astrotomic/laravel-translatable](https://github.com/Astrotomic/laravel-translatable) — no package code changes.

## 1. Translations table

```php
Schema::create('notify_template_translations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('notify_template_id')->constrained('notify_templates')->cascadeOnDelete();
    $table->string('locale', 10);
    $table->text('subject')->nullable();
    $table->longText('body')->nullable();
    $table->unique(['notify_template_id', 'locale']);
});
```

The package migration contains the same block commented out. `notify_templates.subject` / `body` may stay as they are.

## 2. Model

```php
namespace App\Models;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Fomvasss\NotifyTemplates\Models\NotifyTemplate as BaseNotifyTemplate;

class NotifyTemplate extends BaseNotifyTemplate implements TranslatableContract
{
    use Translatable;

    public array $translatedAttributes = ['subject', 'body'];
}
```

## 3. Config

```php
'models' => [
    'notify_template' => \App\Models\NotifyTemplate::class,
    // keep the other models
    'notify_role_subscription' => \Fomvasss\NotifyTemplates\Models\NotifyRoleSubscription::class,
    'notify_user_setting' => \Fomvasss\NotifyTemplates\Models\NotifyUserSetting::class,
    'notify_log' => \Fomvasss\NotifyTemplates\Models\NotifyLog::class,
],
```

The whole `models` array is replaced by a published config, so list all four.

Now `$template->subject` returns the current locale's translation, and `toMail()` / `getMessengerBody()` use it unchanged. Missing translations fall back according to astrotomic's own settings.

## Recipient's locale

Laravel switches the locale per notifiable before calling `toMail()` / `toTelegram()` — also in queued notifications — when the notifiable implements `HasLocalePreference`:

```php
use Illuminate\Contracts\Translation\HasLocalePreference;

class User extends Authenticatable implements HasLocalePreference
{
    public function preferredLocale(): string
    {
        return $this->locale ?? config('app.locale');
    }
}
```

Or per send: `$user->notify((new OrderOrderedNotify($order, 'client'))->locale('uk'))`.

## Buttons

Template `options` are stored on `notify_templates`, not in the translations table. Translate button text with a locale map instead — see [Messenger buttons](messenger-buttons.md#multilingual-text).
