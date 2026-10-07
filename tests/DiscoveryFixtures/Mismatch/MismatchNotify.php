<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests\DiscoveryFixtures\Mismatch;

use Fomvasss\NotifyTemplates\Notifications\BaseNotify;

final class MismatchNotify extends BaseNotify
{
    public static function typeDefinition(): array
    {
        return ['key' => 'SomethingElse', 'name' => 'Mismatch', 'group' => 'test'];
    }
}
