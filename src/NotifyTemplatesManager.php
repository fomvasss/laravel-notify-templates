<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates;

use Fomvasss\NotifyTemplates\Models\NotifyLog;
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

            if ($definition === []) {
                continue;
            }

            // the class sends under notifyKey(), the registry answers under 'key' — two different
            // values made settings, user_configurable and buttons silently not found at send time
            if (empty($definition['key'])) {
                $definition['key'] = $class::notifyKey();
            }

            if ($definition['key'] !== $class::notifyKey()) {
                throw new \LogicException(sprintf(
                    '%s: typeDefinition() key "%s" differs from notifyKey() "%s". Make them equal — override notifyKey() or fix the key.',
                    $class,
                    $definition['key'],
                    $class::notifyKey(),
                ));
            }

            $this->registerType($definition);
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

    /**
     * Template slots a notify type renders — the distinct slots of its channels, in channel order.
     *
     * @return list<string>
     */
    public function getTypeSlots(string $notifyKey): array
    {
        return array_values(array_unique(array_map(
            fn(string $channel) => $this->getChannel($channel)['slot'],
            $this->getTypeChannels($notifyKey),
        )));
    }

    // -------------------------------------------------------------------------
    // Channel registry
    // -------------------------------------------------------------------------

    /**
     * Every channel of config('notify-templates.channels') with its metadata, keyed by slug.
     *
     * @return array<string, array{key: string, label: string, slot: string}>
     */
    public function getChannels(): array
    {
        $channels = [];

        foreach (config('notify-templates.channels', []) as $channel) {
            $channels[$channel] = $this->getChannel($channel);
        }

        return $channels;
    }

    /**
     * Template slots of every configured channel with their metadata, keyed by slot, in channel order.
     *
     * @return array<string, array{key: string, label: string, subject: bool}>
     */
    public function getSlots(): array
    {
        $slots = [];

        foreach ($this->getChannels() as $channel) {
            $slots[$channel['slot']] ??= $this->getSlot($channel['slot']);
        }

        return $slots;
    }

    /**
     * Metadata of one template slot: config('notify-templates.slot_options') over the defaults.
     *
     * @return array{key: string, label: string, subject: bool}
     */
    public function getSlot(string $slot): array
    {
        return array_replace(
            ['label' => ucfirst($slot), 'subject' => $slot === 'mail'],
            config("notify-templates.slot_options.{$slot}", []),
            ['key' => $slot],
        );
    }

    /**
     * Metadata of one channel: config('notify-templates.channel_options') over the defaults.
     * Works for a slug missing from `channels` too — the defaults alone.
     *
     * @return array{key: string, label: string, slot: string}
     */
    public function getChannel(string $channel): array
    {
        return array_replace(
            ['label' => ucfirst($channel), 'slot' => $channel === 'mail' ? 'mail' : 'messenger'],
            config("notify-templates.channel_options.{$channel}", []),
            ['key' => $channel],
        );
    }

    // -------------------------------------------------------------------------
    // Tenant resolution
    // -------------------------------------------------------------------------

    /**
     * Falls back to config('notify-templates.tenant_id') when no explicit tenantId is passed;
     * '' asks for the global rows only, skipping the fallback. The config value can be a plain
     * string/int or a callable returning one.
     */
    public function resolveTenantId(?string $tenantId): ?string
    {
        if ($tenantId !== null) {
            return $tenantId === '' ? null : $tenantId;
        }

        $configured = config('notify-templates.tenant_id');

        // a plain string is a callable only as 'Class::method' — otherwise a tenant id that
        // happens to be a PHP function name ('date', 'max') would be called
        if ($configured instanceof \Closure || is_array($configured) || (is_string($configured) && str_contains($configured, '::'))) {
            $configured = $configured();
        }

        return $configured === null || $configured === '' ? null : (string) $configured;
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
    // Messenger buttons
    // -------------------------------------------------------------------------

    /**
     * Link buttons shown under a messenger message, raw (tokens not substituted):
     * template options.buttons → typeDefinition buttons_by_role[role] → typeDefinition buttons.
     * A template or role entry that is an array wins even when empty — [] means "no buttons".
     * `text` is a string or a locale map (see localizeButtonText()).
     *
     * @param array|null $type  typeDefinition() of the notify; defaults to the registered type
     * @return list<array{text: string|array<string, string>, url: string}>
     */
    /**
     * Template of a messenger slot: the slot's own row when it has a body, otherwise the `messenger` row.
     * One channel (`'slot' => 'telegram'` in channel_options) can override the shared messenger text
     * without duplicating every template; an empty override is no override.
     */
    public function resolveMessengerTemplate(
        string $notifyKey,
        string $slot = 'messenger',
        ?string $roleKey = null,
        ?string $tenantId = null,
    ): ?NotifyTemplateModel {
        if ($slot !== 'messenger') {
            $template = $this->resolveTemplate($notifyKey, $slot, $roleKey, $tenantId);

            // resolve() matches channel-less rows too — those are the shared fallback, not this slot's override
            if ($template?->channel === $slot && trim((string) $template->body) !== '') {
                return $template;
            }
        }

        return $this->resolveTemplate($notifyKey, 'messenger', $roleKey, $tenantId);
    }

    public function resolveButtons(
        string $notifyKey,
        ?string $roleKey = null,
        ?string $tenantId = null,
        string $channel = 'messenger',
        ?array $type = null,
    ): array {
        $buttons = $this->messengerOption($notifyKey, $channel, $roleKey, $tenantId, 'buttons');

        if (!is_array($buttons)) {
            $type ??= $this->getType($notifyKey) ?? [];
            $buttons = $type['buttons_by_role'][$roleKey ?? ''] ?? $type['buttons'] ?? [];
        }

        return array_values(array_filter(
            $buttons,
            fn($button) => is_array($button) && $this->localizeButtonText($button['text'] ?? '') !== '' && trim((string) ($button['url'] ?? '')) !== '',
        ));
    }

    /**
     * Button text for a locale. A plain string is returned as is; a locale map
     * (['uk' => 'Оплатити', 'en' => 'Pay']) resolves to the requested locale (the current app
     * locale by default — Laravel switches it per notifiable that has a preferred locale), then
     * app.fallback_locale, then the first non-empty entry. Empty entries count as missing.
     */
    public function localizeButtonText(mixed $text, ?string $locale = null): string
    {
        if (!is_array($text)) {
            return trim((string) $text);
        }

        $texts = array_filter(array_map(fn($value) => trim((string) $value), $text), fn($value) => $value !== '');

        return $texts[$locale ?? app()->getLocale()]
            ?? $texts[(string) config('app.fallback_locale')]
            ?? (string) reset($texts);
    }

    /**
     * Buttons per row: template options.buttons_columns → typeDefinition buttons_columns → 1.
     */
    public function resolveButtonsColumns(
        string $notifyKey,
        ?string $roleKey = null,
        ?string $tenantId = null,
        string $channel = 'messenger',
        ?array $type = null,
    ): int {
        $columns = $this->messengerOption($notifyKey, $channel, $roleKey, $tenantId, 'buttons_columns');
        $columns ??= ($type ?? $this->getType($notifyKey) ?? [])['buttons_columns'] ?? 1;

        return max(1, (int) $columns);
    }

    // Option of the slot's own row, then of the `messenger` row — buttons set on the shared text keep
    // working for a channel whose override only changes the body
    private function messengerOption(string $notifyKey, string $slot, ?string $roleKey, ?string $tenantId, string $key): mixed
    {
        if ($slot !== 'messenger') {
            $template = $this->resolveTemplate($notifyKey, $slot, $roleKey, $tenantId);

            if ($template?->channel === $slot && ($value = $template->getOption($key)) !== null) {
                return $value;
            }
        }

        return $this->resolveTemplate($notifyKey, 'messenger', $roleKey, $tenantId)?->getOption($key);
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

    // -------------------------------------------------------------------------
    // Delivery log
    // -------------------------------------------------------------------------

    /**
     * Apply a provider delivery report (webhook or status poll) to the logged message.
     * The status only ever moves forward — a stale or out-of-order report is ignored.
     * Returns false when no log row matches or the report was ignored.
     *
     * @param string $status  One of NotifyLog::statuses() except 'pending'
     */
    public function updateDelivery(string $channel, string $externalId, string $status, array $payload = []): bool
    {
        if ($status === NotifyLog::STATUS_PENDING || !in_array($status, NotifyLog::statuses(), true)) {
            throw new \InvalidArgumentException("Invalid delivery status \"{$status}\".");
        }

        /** @var class-string<NotifyLog> $class */
        $class = config('notify-templates.models.notify_log', NotifyLog::class);

        $log = $class::query()
            ->where('channel', $channel)
            ->where('external_id', $externalId)
            // not latest('id') — host apps may use UUID keys
            ->latest()
            ->first();

        if (!$log || !$log->canMoveTo($status)) {
            return false;
        }

        return $log->update([
            'status' => $status,
            'payload' => $payload ?: null,
            'status_updated_at' => now(),
        ]);
    }
}
