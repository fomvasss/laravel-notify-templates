<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests;

use Fomvasss\NotifyTemplates\Models\NotifyTemplate;
use Fomvasss\NotifyTemplates\Tests\Fixtures\ButtonsNotify;

/**
 * A channel with its own slot (`'slot' => 'telegram'`) overrides the shared messenger text only
 * where its row has a body; everything else comes from the `messenger` row.
 */
class MessengerSlotTest extends TestCase
{
    public function test_without_own_row_slot_reads_messenger(): void
    {
        $this->row('messenger', 'Shared');

        $this->assertSame('Shared', (new ButtonsNotify('client'))->body('telegram'));
    }

    public function test_own_row_overrides_messenger(): void
    {
        $this->row('messenger', 'Shared');
        $this->row('telegram', 'Telegram only');

        $notify = new ButtonsNotify('client');

        $this->assertSame('Telegram only', $notify->body('telegram'));
        $this->assertSame('Shared', $notify->body());
    }

    public function test_empty_own_row_is_no_override(): void
    {
        $this->row('messenger', 'Shared');
        $this->row('telegram', '');

        $this->assertSame('Shared', (new ButtonsNotify('client'))->body('telegram'));
    }

    public function test_channel_less_row_is_not_an_override(): void
    {
        $this->row(null, 'Any channel');
        $this->row('messenger', 'Shared');

        $this->assertSame('Shared', (new ButtonsNotify('client'))->body('telegram'));
    }

    public function test_falls_back_to_mail_row_without_messenger(): void
    {
        $this->row('mail', 'Mail body');

        $this->assertSame('Mail body', (new ButtonsNotify('client'))->body('telegram'));
    }

    public function test_buttons_of_messenger_row_apply_to_override_without_buttons(): void
    {
        $this->row('messenger', 'Shared', ['buttons' => [['text' => 'Track', 'url' => 'https://x.example.com']], 'buttons_columns' => 2]);
        $this->row('telegram', 'Telegram only');

        $notify = new ButtonsNotify('client');

        $this->assertSame('Track', $notify->buttons(null, 'telegram')[0]['text']);
        $this->assertSame(2, $notify->columns('telegram'));
    }

    public function test_own_row_buttons_win(): void
    {
        $this->row('messenger', 'Shared', ['buttons' => [['text' => 'Track', 'url' => 'https://x.example.com']]]);
        $this->row('telegram', 'Telegram only', ['buttons' => []]);

        $this->assertSame([], (new ButtonsNotify('client'))->buttons(null, 'telegram'));
    }

    private function row(?string $slot, string $body, ?array $options = null): void
    {
        NotifyTemplate::create([
            'notify_key' => 'Buttons',
            'channel' => $slot,
            'role_key' => 'client',
            'body' => $body,
            'options' => $options,
        ]);
    }
}
