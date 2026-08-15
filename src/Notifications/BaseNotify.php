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
        return preg_replace('/Notify$/', '', class_basename(static::class));
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
        // The notifiable itself opted out of this notify type — independent of role
        // subscription/channels below.
        if (!$this->manager()->isNotifyEnabled($this->getNotifyKey(), $notifiable)) {
            return [];
        }

        $userChannels = method_exists($notifiable, 'getNotifyChannels')
            ? $notifiable->getNotifyChannels()
            : [];

        // Per-type channel override (notify_user_settings.channels) narrows the notifiable's
        // global channel preference further, e.g. "this one type only via telegram".
        $typeChannels = $this->manager()->resolveNotifyUserChannels($this->getNotifyKey(), $notifiable);
        if ($typeChannels !== null) {
            $userChannels = $userChannels ? array_intersect($userChannels, $typeChannels) : $typeChannels;

            // An override that leaves nothing ([] stored, or no overlap with the global
            // preference) is an explicit opt-out of every channel for this type. Without this
            // check the empty array would fall through to resolveChannels(), which treats
            // empty $userChannels as "no preference" and returns the full subscription list.
            if (!$userChannels) {
                return [];
            }
        }

        $channels = $this->manager()->resolveChannels(
            $this->getNotifyKey(),
            $this->roleKey,
            $this->tenantId,
            $userChannels,
        );

        $result = [];

        foreach ($channels as $channel) {
            $mapped = $this->mapChannel($channel, $notifiable);

            if ($mapped !== null) {
                $result[] = $mapped;
            }
        }

        // Guaranteed-delivery fallback ONLY for types the user can't configure (OTP and the
        // like) — those must go out even with no subscription row. For everything else an
        // empty result is a legitimate "don't send": user opt-outs and is_active=false must
        // not be silently overridden with default_channels.
        if (!$result && !$this->manager()->isUserConfigurable($this->getNotifyKey())) {
            $result = config('notify-templates.default_channels', ['mail']);
        }

        if ($this->onlyChannels) {
            $result = array_values(array_intersect($result, $this->onlyChannels));
        }

        if ($this->exceptChannels) {
            $result = array_values(array_diff($result, $this->exceptChannels));
        }

        return $result;
    }

    /**
     * Map a resolved channel slug to what Laravel's Notification dispatcher expects — a channel
     * name or class-string — or null to skip it (e.g. the notifiable has no route for it).
     *
     * THE extension point for host channels: override in your app's base notification class
     * (or a trait) with a match on your slugs and fall through to parent::mapChannel() for the
     * rest. Everything else — the opt-out gate, user channel preferences, subscription
     * resolution, the guaranteed-delivery fallback, only()/except() — stays in via() and keeps
     * applying to your channels too. Do NOT copy via() into the host app: every package update
     * to the resolution chain would then need a manual sync.
     *
     *   protected function mapChannel(string $channel, mixed $notifiable): ?string
     *   {
     *       return match ($channel) {
     *           'telegram' => $notifiable->routeNotificationForTelegram() ? 'telegram' : null,
     *           'sms' => $notifiable->phone ? TurboSmsChannel::class : null,
     *           default => parent::mapChannel($channel, $notifiable),
     *       };
     *   }
     */
    protected function mapChannel(string $channel, mixed $notifiable): ?string
    {
        return match ($channel) {
            // mail is silently skipped when the notifiable has no email property
            'mail' => !empty($notifiable->email) ? 'mail' : null,
            'database', 'broadcast' => $channel,
            default => null,
        };
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
