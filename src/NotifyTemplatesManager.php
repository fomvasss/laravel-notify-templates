<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates;

use Fomvasss\NotifyTemplates\Models\NotifyRoleSubscription;
use Fomvasss\NotifyTemplates\Models\NotifyTemplate as NotifyTemplateModel;
use Fomvasss\NotifyTemplates\Models\NotifyUserSetting;
use Fomvasss\NotifyTemplates\Notifications\BaseNotify;


class NotifyTemplatesManager
{
    /** @var array<string, array> */
    private array $types = [];

    // -------------------------------------------------------------------------
    // Type registry
    // -------------------------------------------------------------------------

    /**
     * @param array{key: string, name: string, group: string, settings?: array, tokens?: array} $type
     */
    public function registerType(array $type): void
    {
        if (empty($type['key'])) {
            throw new \InvalidArgumentException('Notify type must have a non-empty "key".');
        }

        $this->types[$type['key']] = $type;
    }

    /** @param array<array{key: string, ...}> $types */
    public function registerTypes(array $types): void
    {
        foreach ($types as $type) {
            $this->registerType($type);
        }
    }

    public function discoverIn(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = $this->classFromFile($file->getPathname());

            if (!$class || !class_exists($class) || !is_subclass_of($class, BaseNotify::class)) {
                continue;
            }

            $definition = $class::typeDefinition();

            if (!empty($definition['key'])) {
                $this->registerType($definition);
            }
        }
    }

    private function classFromFile(string $file): ?string
    {
        $content = file_get_contents($file);

        if (!preg_match('/^namespace\s+(.+?);/m', $content, $ns)) {
            return null;
        }

        if (!preg_match('/^(?:final\s+)?class\s+(\w+)/m', $content, $cl)) {
            return null;
        }

        return $ns[1] . '\\' . $cl[1];
    }

    /** @return array<string, array> */
    public function getTypes(?string $group = null): array
    {
        if ($group === null) {
            return $this->types;
        }

        return array_filter($this->types, fn($t) => ($t['group'] ?? null) === $group);
    }

    public function getType(string $key): ?array
    {
        return $this->types[$key] ?? null;
    }

    /**
     * Channels supported by a notify type.
     * Falls back to config('notify-templates.channels') if not defined in typeDefinition.
     *
     * @return array<string>
     */
    public function getTypeChannels(string $notifyKey): array
    {
        $type = $this->getType($notifyKey);

        return ($type['channels'] ?? []) ?: config('notify-templates.channels', []);
    }

    // -------------------------------------------------------------------------
    // Tenant resolution
    // -------------------------------------------------------------------------

    /**
     * Falls back to config('notify-templates.tenant_id') when no explicit tenantId is passed.
     * The config value can be a plain string or a callable returning one.
     */
    protected function resolveTenantId(?string $tenantId): ?string
    {
        if ($tenantId !== null) {
            return $tenantId;
        }

        $configured = config('notify-templates.tenant_id');

        return is_callable($configured) ? $configured() : $configured;
    }

    // -------------------------------------------------------------------------
    // Template resolution
    // -------------------------------------------------------------------------

    /**
     * @param string $channel  Template slot: 'mail', 'messenger', 'sms', or any custom slot
     */
    public function resolveTemplate(
        string $notifyKey,
        string $channel,
        ?string $roleKey = null,
        ?string $tenantId = null,
    ): ?NotifyTemplateModel {
        /** @var class-string<NotifyTemplateModel> $class */
        $class = config('notify-templates.models.notify_template', NotifyTemplateModel::class);

        return $class::resolve($notifyKey, $channel, $roleKey, $this->resolveTenantId($tenantId));
    }

    // -------------------------------------------------------------------------
    // Channel & delay resolution
    // -------------------------------------------------------------------------

    /**
     * Resolve delivery channels for a role+notify.
     * If the user has channel preferences, the result is their intersection with the subscription channels
     * (user can opt out of channels but not add new ones).
     * Returns empty array if the subscription is inactive or not found.
     */
    public function resolveChannels(
        string $notifyKey,
        string $roleKey,
        ?string $tenantId = null,
        array $userChannels = [],
    ): array {
        /** @var class-string<NotifyRoleSubscription> $subClass */
        $subClass = config('notify-templates.models.notify_role_subscription', NotifyRoleSubscription::class);
        $subscription = $subClass::resolve($roleKey, $notifyKey, $this->resolveTenantId($tenantId));

        if (!$subscription || !$subscription->is_active) {
            return [];
        }

        $channels = $subscription->channels ?: config('notify-templates.default_channels', ['mail']);

        if ($userChannels) {
            return array_values(array_intersect($channels, $userChannels));
        }

        return $channels;
    }

    /**
     * Delay in seconds (options.delay is stored in minutes).
     */
    public function resolveDelay(
        string $notifyKey,
        string $roleKey,
        ?string $tenantId = null,
    ): int {
        /** @var class-string<NotifyRoleSubscription> $subClass */
        $subClass = config('notify-templates.models.notify_role_subscription', NotifyRoleSubscription::class);
        $subscription = $subClass::resolve($roleKey, $notifyKey, $this->resolveTenantId($tenantId));

        return $subscription?->getDelaySeconds() ?? 0;
    }

    /**
     * false only when the type's own typeDefinition() explicitly opts out via
     * 'user_configurable' => false (e.g. OTP/security codes — a notifiable must not be able to
     * turn those off, whether by a UI toggle or a stray notify_user_settings row). Default true.
     */
    public function isUserConfigurable(string $notifyKey): bool
    {
        return $this->getType($notifyKey)['user_configurable'] ?? true;
    }

    /**
     * Whether the notifiable itself has opted out of this notify type — independent of role
     * subscription/channels. Works for any Eloquent model, no interface/trait required on it.
     */
    public function isNotifyEnabled(string $notifyKey, mixed $notifiable): bool
    {
        if (!$this->isUserConfigurable($notifyKey)) {
            return true;
        }

        /** @var class-string<NotifyUserSetting> $class */
        $class = config('notify-templates.models.notify_user_setting', NotifyUserSetting::class);

        return $class::isEnabledFor($notifiable, $notifyKey);
    }

    /**
     * Per-notifiable, per-notify-type channel override (notify_user_settings.channels).
     * Null = no override — caller falls back to the notifiable's own channel preference.
     */
    public function resolveNotifyUserChannels(string $notifyKey, mixed $notifiable): ?array
    {
        if (!$this->isUserConfigurable($notifyKey)) {
            return null;
        }

        /** @var class-string<NotifyUserSetting> $class */
        $class = config('notify-templates.models.notify_user_setting', NotifyUserSetting::class);

        return $class::channelsFor($notifiable, $notifyKey);
    }
}
