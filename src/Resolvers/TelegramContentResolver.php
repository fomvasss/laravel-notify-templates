<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Resolvers;

use Fomvasss\NotifyTemplates\Contracts\ContentResolverInterface;
use Fomvasss\NotifyTemplates\Resolvers\Concerns\ReadsTelegramResults;

class TelegramContentResolver implements ContentResolverInterface
{
    use ReadsTelegramResults;

    public function resolve(mixed $response): array
    {
        $texts = array_filter(array_map(
            fn($result) => $result['text'] ?? $result['caption'] ?? null,
            $this->results($response),
        ));

        return $texts ? ['body' => implode("\n", $texts)] : [];
    }
}
