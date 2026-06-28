<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests\Fixtures\Sub;

use Fomvasss\NotifyTemplates\Notifications\BaseNotify;

final class NestedNotify extends BaseNotify
{
    public function __construct(protected string $roleKey) {}

    public static function notifyKey(): string
    {
        return 'NestedEvent';
    }

    public static function typeDefinition(): array
    {
        return [
            'key' => 'NestedEvent',
            'name' => 'Nested event',
            'group' => 'test',
        ];
    }
}
