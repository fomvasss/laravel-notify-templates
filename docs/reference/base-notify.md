# BaseNotify

`Fomvasss\NotifyTemplates\Notifications\BaseNotify` — abstract, extends `Illuminate\Notifications\Notification`.

## Properties

| Property | Type | Description |
|---|---|---|
| `$roleKey` | `string` (protected, no default) | Role whose subscription and templates apply. Must be set before sending |
| `$tenantId` | `?string` (protected) | Tenant; `null` = `config('notify-templates.tenant_id')` |
| `$onlyChannels`, `$exceptChannels` | `array` (protected) | Set by `only()` / `except()` |

## Static methods

| Method | Description |
|---|---|
| `typeDefinition(): array` | Type metadata, see [typeDefinition() keys](type-definition.md). Default `[]` — such a class is not registered by discovery |
| `notifyKey(): string` | Type key used at send time. Default: class name without the trailing `Notify` |

## Public methods

| Method | Returns | Description |
|---|---|---|
| `only(array $channels)` | `static` | Keep only these channels of the resolved ones |
| `except(array $channels)` | `static` | Remove these channels from the resolved ones |
| `via(mixed $notifiable)` | `array` | Channel resolution, see [Channel resolution](../usage/channels.md) |
| `toMail(mixed $notifiable)` | `MailMessage` | Subject and body of the `mail` slot through `prepareText()`; body as plain text — a blank line starts a paragraph (one `line()` each), a line break becomes `<br>`, HTML is escaped |
| `toArray(mixed $notifiable)` | `array` | `['message' => strip_tags(getMessengerBody())]` for `database` / `broadcast` |
| `getNotifyKey()` | `string` | `static::notifyKey()` |
| `getRoleKey()` | `?string` | `$roleKey`, or `null` when not set |
| `getTenantId()` | `?string` | `$tenantId` as set on the instance (without the config fallback) |
| `getSubjectDefault()` | `string` | `typeDefinition()['defaults']['mail']['subject']` or `''` |
| `getBodyDefault()` | `string` | `typeDefinition()['defaults']['mail']['body']` or `''` |

## Protected methods — hooks and helpers

| Method | Returns | Description |
|---|---|---|
| `prepareText(string $text, mixed $notifiable)` | `string` | Token substitution hook. Default: returns `$text` unchanged |
| `mapChannel(string $channel, mixed $notifiable)` | `?string` | Slug → Laravel channel, or `null` to drop. Built-in: `mail` when the notifiable has a mail route (`routeNotificationFor('mail')`, else `$notifiable->email`), `database`, `broadcast`. See [Custom channels](../usage/custom-channels.md) |
| `resolveTemplate(string $channel)` | `?NotifyTemplate` | Template row for a slot with this notification's key, role and tenant |
| `getMessengerBody(mixed $notifiable)` | `string` | `messenger` slot → `mail` slot → `defaults.mail.body`, through `prepareText()` |
| `getMessengerButtons(mixed $notifiable)` | `array` | `[['text' => string, 'url' => string], ...]` — localized, through `prepareText()`, unsendable urls dropped |
| `getMessengerButtonsColumns()` | `int` | Buttons per row |
| `isSendableButtonUrl(string $url)` | `bool` | Absolute `http(s)` url whose host has a dot and doesn't end in `.test`, `.local`, `.localhost` |
| `manager()` | `NotifyTemplatesManager` | The singleton |

`getMessengerButtons()` and `getMessengerButtonsColumns()` use the class's own `static::typeDefinition()`, not the registry, so buttons work even for a type that isn't registered.
