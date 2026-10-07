<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests\DiscoveryFixtures\KeyFromClass;

use Fomvasss\NotifyTemplates\Notifications\BaseNotify;

final class OrderShippedNotify extends BaseNotify
{
    public static function typeDefinition(): array
    {
        return ['name' => 'Order shipped', 'group' => 'order'];
    }
}
