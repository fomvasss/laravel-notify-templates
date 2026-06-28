<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates;

use Fomvasss\NotifyTemplates\Models\NotifyRoleSubscription;
use Fomvasss\NotifyTemplates\Models\NotifyTemplate;
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
        foreach (glob(rtrim($path, '/') . '/*.php') as $file) {
            $class = $this->classFromFile($file);

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
    ): ?NotifyTemplate {
        /** @var class-string<NotifyTemplate> $class */
        $class = config('notifytemplates.models.notify_template', NotifyTemplate::class);

        return $class::resolve($notifyKey, $channel, $roleKey, $tenantId);
    }

    // -------------------------------------------------------------------------
    // Channel & delay resolution
    // -------------------------------------------------------------------------

    /**
     * Resolve delivery channels for a role+notify, merged with per-user channels.
     * Returns empty array if the subscription is inactive or not found.
     */
    public function resolveChannels(
        string $notifyKey,
        string $roleKey,
        ?string $tenantId = null,
        array $userChannels = [],
    ): array {
        $subscription = NotifyRoleSubscription::resolve($roleKey, $notifyKey, $tenantId);

        if (!$subscription || !$subscription->is_active) {
            return [];
        }

        return array_unique(array_merge(
            $subscription->channels ?? [],
            $userChannels,
        ));
    }

    /**
     * Delay in seconds (options.delay is stored in minutes).
     */
    public function resolveDelay(
        string $notifyKey,
        string $roleKey,
        ?string $tenantId = null,
    ): int {
        $subscription = NotifyRoleSubscription::resolve($roleKey, $notifyKey, $tenantId);

        return $subscription?->getDelaySeconds() ?? 0;
    }
}
