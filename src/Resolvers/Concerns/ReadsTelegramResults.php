<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Resolvers\Concerns;

/**
 * laravel-notification-channels/telegram returns the decoded Bot API response —
 * {ok, result: {message_id, text, ...}}, or a list of those when the message was chunked.
 */
trait ReadsTelegramResults
{
    /** @return array<int, array> */
    protected function results(mixed $response): array
    {
        if (!is_array($response)) {
            return [];
        }

        $responses = array_is_list($response) ? $response : [$response];

        return array_values(array_filter(array_map(
            fn($item) => is_array($item) && is_array($item['result'] ?? null) ? $item['result'] : null,
            $responses,
        )));
    }
}
