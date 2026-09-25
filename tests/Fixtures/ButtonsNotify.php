<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests\Fixtures;

use Fomvasss\NotifyTemplates\Notifications\BaseNotify;

final class ButtonsNotify extends BaseNotify
{
    public function __construct(protected string $roleKey, private readonly string $orderUrl = 'https://shop.example.com/orders/7') {}

    public static function typeDefinition(): array
    {
        return [
            'key' => 'Buttons',
            'name' => 'Buttons',
            'group' => 'test',
            'buttons' => [
                ['text' => 'Open order', 'url' => '[order:url]'],
            ],
            'buttons_by_role' => [
                'admin' => [['text' => 'Admin', 'url' => 'https://admin.example.com/orders/7']],
                'guest' => [],
            ],
        ];
    }

    protected function prepareText(string $text, mixed $notifiable): string
    {
        return str_replace('[order:url]', $this->orderUrl, $text);
    }

    public function buttons(mixed $notifiable = null): array
    {
        return $this->getMessengerButtons($notifiable);
    }

    public function columns(): int
    {
        return $this->getMessengerButtonsColumns();
    }
}
