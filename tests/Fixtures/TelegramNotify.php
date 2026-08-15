<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests\Fixtures;

use Fomvasss\NotifyTemplates\Notifications\BaseNotify;

/**
 * Host-app style extension: adds a 'telegram' channel via the mapChannel() hook.
 */
final class TelegramNotify extends BaseNotify
{
    public function __construct(protected string $roleKey) {}

    public static function notifyKey(): string
    {
        return 'SampleEvent';
    }

    public static function typeDefinition(): array
    {
        return [
            'key' => 'SampleEvent',
            'name' => 'Sample event',
            'group' => 'test',
            'weight' => 10,
        ];
    }

    protected function mapChannel(string $channel, mixed $notifiable): ?string
    {
        return match ($channel) {
            'telegram' => !empty($notifiable->telegram_id) ? 'telegram' : null,
            default => parent::mapChannel($channel, $notifiable),
        };
    }
}
