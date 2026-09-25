<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests;

use Fomvasss\NotifyTemplates\Models\NotifyTemplate;
use Fomvasss\NotifyTemplates\Resolvers\TelegramContentResolver;
use Fomvasss\NotifyTemplates\Tests\Fixtures\ButtonsNotify;

class ButtonsTest extends TestCase
{
    public function test_type_buttons_with_tokens_applied(): void
    {
        $this->assertSame(
            [['text' => 'Open order', 'url' => 'https://shop.example.com/orders/7']],
            (new ButtonsNotify('client'))->buttons(),
        );
    }

    public function test_role_buttons_replace_type_buttons(): void
    {
        $this->assertSame('Admin', (new ButtonsNotify('admin'))->buttons()[0]['text']);
        $this->assertSame([], (new ButtonsNotify('guest'))->buttons());
    }

    public function test_template_buttons_win_over_type_definition(): void
    {
        $this->template(['buttons' => [['text' => 'Track', 'url' => '[order:url]/track'], ['text' => '', 'url' => 'https://x.example.com']], 'buttons_columns' => 2]);

        $notify = new ButtonsNotify('client');

        $this->assertSame([['text' => 'Track', 'url' => 'https://shop.example.com/orders/7/track']], $notify->buttons());
        $this->assertSame(2, $notify->columns());
    }

    public function test_empty_template_buttons_mean_no_buttons(): void
    {
        $this->template(['buttons' => []]);

        $this->assertSame([], (new ButtonsNotify('client'))->buttons());
    }

    public function test_template_without_buttons_option_falls_back_to_type(): void
    {
        $this->template(null);

        $this->assertCount(1, (new ButtonsNotify('client'))->buttons());
        $this->assertSame(1, (new ButtonsNotify('client'))->columns());
    }

    public function test_unsendable_urls_are_dropped(): void
    {
        foreach (['', 'http://shop.test/orders/7', 'http://localhost/x', '/orders/7', 'ftp://shop.example.com'] as $url) {
            $this->assertSame([], (new ButtonsNotify('client', $url))->buttons(), $url);
        }
    }

    public function test_telegram_log_body_lists_url_buttons(): void
    {
        $response = ['ok' => true, 'result' => [
            'message_id' => 1,
            'text' => 'Hello',
            'reply_markup' => ['inline_keyboard' => [[['text' => 'Pay', 'url' => 'https://pay.example.com']]]],
        ]];

        $this->assertSame(['body' => "Hello\n\n[Pay] https://pay.example.com"], (new TelegramContentResolver())->resolve($response));
    }

    private function template(?array $options): void
    {
        NotifyTemplate::create([
            'notify_key' => 'Buttons',
            'channel' => 'messenger',
            'role_key' => 'client',
            'body' => 'Body',
            'options' => $options,
        ]);
    }
}
