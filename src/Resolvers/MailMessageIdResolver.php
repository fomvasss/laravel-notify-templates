<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Resolvers;

use Fomvasss\NotifyTemplates\Contracts\ExternalIdResolverInterface;
use Illuminate\Mail\SentMessage;

class MailMessageIdResolver implements ExternalIdResolverInterface
{
    public function resolve(mixed $response): ?string
    {
        return $response instanceof SentMessage ? $response->getMessageId() : null;
    }
}
