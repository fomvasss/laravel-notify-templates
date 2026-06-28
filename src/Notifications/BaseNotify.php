<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Notifications;

use Fomvasss\NotifyTemplates\Models\NotifyTemplate;
use Fomvasss\NotifyTemplates\NotifyTemplatesManager;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class BaseNotify extends Notification
{
    protected string $roleKey;

    protected ?string $tenantId = null;

    protected array $onlyChannels = [];

    protected array $exceptChannels = [];

    public function only(array $channels): static
    {
        $this->onlyChannels = $channels;
        return $this;
    }

    public function except(array $channels): static
    {
        $this->exceptChannels = $channels;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Contract — implement in each concrete notify class
    // -------------------------------------------------------------------------

    // Override only if class name doesn't follow the {NotifyKey}Notify convention.
    public static function notifyKey(): string
    {
        return str_replace('Notify', '', class_basename(static::class));
    }

    public function getNotifyKey(): string
    {
        return static::notifyKey();
    }

    public static function typeDefinition(): array
    {
        return [];
    }

    public function getBodyDefault(): string
    {
        return static::typeDefinition()['defaults']['mail']['body'] ?? '';
    }

    public function getSubjectDefault(): string
    {
        return static::typeDefinition()['defaults']['mail']['subject'] ?? '';
    }

    // -------------------------------------------------------------------------
    // Hook — override in concrete class for token/shortcode processing
    // -------------------------------------------------------------------------

    protected function prepareText(string $text, mixed $notifiable): string
    {
        return $text;
    }

    // -------------------------------------------------------------------------
    // Channel resolution
    // -------------------------------------------------------------------------

    public function via(mixed $notifiable): array
    {
        $userChannels = method_exists($notifiable, 'getNotifyChannels')
            ? $notifiable->getNotifyChannels()
            : [];

        $channels = $this->manager()->resolveChannels(
            $this->getNotifyKey(),
            $this->roleKey,
            $this->tenantId,
            $userChannels,
        );

        $result = [];

        // mail is silently skipped when the notifiable has no email property
        if (in_array('mail', $channels) && !empty($notifiable->email)) {
            $result[] = 'mail';
        }

        if (in_array('database', $channels)) {
            $result[] = 'database';
        }

        if (in_array('broadcast', $channels)) {
            $result[] = 'broadcast';
        }

        // Додаткові канали (telegram, sms тощо) — через трейт у конкретному класі, який викликає parent::via().

        $result = $result ?: config('notify-templates.default_channels', ['mail']);

        if ($this->onlyChannels) {
            $result = array_values(array_intersect($result, $this->onlyChannels));
        }

        if ($this->exceptChannels) {
            $result = array_values(array_diff($result, $this->exceptChannels));
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Mail channel (built-in; override for custom view)
    // -------------------------------------------------------------------------

    public function toMail(mixed $notifiable): MailMessage
    {
        $template = $this->resolveTemplate('mail');

        $subject = $template?->subject ?: $this->getSubjectDefault();
        $body = $template?->body ?: $this->getBodyDefault();

        return (new MailMessage())
            ->subject($this->prepareText($subject, $notifiable))
            ->line($this->prepareText($body, $notifiable));
    }

    // -------------------------------------------------------------------------
    // Database / broadcast channel
    // -------------------------------------------------------------------------

    public function toArray(mixed $notifiable): array
    {
        return [
            'message' => strip_tags($this->getMessengerBody($notifiable)),
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers for concrete classes
    // -------------------------------------------------------------------------

    /**
     * Resolved messenger body with prepareText applied.
     * Use in toTelegram(), toTurboSms(), etc. that the host app adds.
     */
    protected function getMessengerBody(mixed $notifiable): string
    {
        $template = $this->resolveTemplate('messenger')
            ?? $this->resolveTemplate('mail');

        $body = $template?->body ?: $this->getBodyDefault();

        return $this->prepareText($body, $notifiable);
    }

    protected function resolveTemplate(string $channel): ?NotifyTemplate
    {
        return $this->manager()->resolveTemplate(
            $this->getNotifyKey(),
            $channel,
            $this->roleKey,
            $this->tenantId,
        );
    }

    protected function manager(): NotifyTemplatesManager
    {
        return app(NotifyTemplatesManager::class);
    }
}
