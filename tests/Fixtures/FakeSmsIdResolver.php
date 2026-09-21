<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests\Fixtures;

use Fomvasss\NotifyTemplates\Contracts\ExternalIdResolverInterface;

final class FakeSmsIdResolver implements ExternalIdResolverInterface
{
    public function resolve(mixed $response): ?string
    {
        return $response['message_id'] ?? null;
    }
}
