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
        $results = $this->results($response);

        $texts = array_filter(array_map(
            fn($result) => $result['text'] ?? $result['caption'] ?? null,
            $results,
        ));

        if (!$texts) {
            return [];
        }

        // Inline url buttons are not part of the text — append them so the log shows where they led
        $buttons = [];
        foreach ($results as $result) {
            foreach ($result['reply_markup']['inline_keyboard'] ?? [] as $row) {
                foreach ($row as $button) {
                    if (isset($button['text'], $button['url'])) {
                        $buttons[] = "[{$button['text']}] {$button['url']}";
                    }
                }
            }
        }

        return ['body' => implode("\n", $texts).($buttons ? "\n\n".implode("\n", $buttons) : '')];
    }
}
