<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Resolvers;

use Fomvasss\NotifyTemplates\Contracts\ContentResolverInterface;
use Illuminate\Mail\SentMessage;
use Symfony\Component\Mime\Email;

class MailContentResolver implements ContentResolverInterface
{
    public function resolve(mixed $response): array
    {
        $email = $response instanceof SentMessage ? $response->getOriginalMessage() : null;

        if (!$email instanceof Email) {
            return [];
        }

        $html = $email->getHtmlBody();
        $text = $email->getTextBody();

        return [
            'subject' => $email->getSubject(),
            'body' => is_string($html) ? $html : (is_string($text) ? $text : null),
        ];
    }
}
