<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Contracts;

/**
 * Extracts what was actually sent — subject and body — from a channel's send() response
 * (NotificationSent::$response), so the delivery log holds the final text with tokens already
 * substituted. Bound per channel in config('notify-templates.log.content_resolvers').
 */
interface ContentResolverInterface
{
    /** @return array{subject?: ?string, body?: ?string} */
    public function resolve(mixed $response): array;
}
