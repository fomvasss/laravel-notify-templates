<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests\Fixtures;

use Fomvasss\NotifyTemplates\Notifications\BaseNotify;

final class SampleNotify extends BaseNotify
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
}
