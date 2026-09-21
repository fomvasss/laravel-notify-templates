<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Contracts;

/**
 * Extracts the provider's message id from what a channel's send() returned
 * (NotificationSent::$response). The id is what later delivery reports are matched by —
 * see NotifyTemplatesManager::updateDelivery(). Bound per channel in
 * config('notify-templates.log.external_id_resolvers').
 */
interface ExternalIdResolverInterface
{
    public function resolve(mixed $response): ?string;
}
