<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Resolvers;

use Fomvasss\NotifyTemplates\Contracts\ExternalIdResolverInterface;
use Fomvasss\NotifyTemplates\Resolvers\Concerns\ReadsTelegramResults;

class TelegramMessageIdResolver implements ExternalIdResolverInterface
{
    use ReadsTelegramResults;

    public function resolve(mixed $response): ?string
    {
        // Chunked message — the first part's id
        $id = $this->results($response)[0]['message_id'] ?? null;

        return $id === null ? null : (string) $id;
    }
}
