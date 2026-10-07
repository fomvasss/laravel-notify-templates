<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests;

use Fomvasss\NotifyTemplates\Models\NotifyRoleSubscription;
use Fomvasss\NotifyTemplates\Models\NotifyUserSetting;
use Fomvasss\NotifyTemplates\Tests\Fixtures\SampleUser;
use Fomvasss\NotifyTemplates\Tests\Fixtures\TelegramNotify;

/**
 * mapChannel() — the host-app extension point for custom channels. The whole via()
 * resolution chain (opt-out gate, user preferences, only()/except()) must apply to
 * channels added through it, without the host duplicating via().
 */
class MapChannelTest extends TestCase
{
    private function sub(array $channels): void
    {
        NotifyRoleSubscription::create([
            'notify_key' => 'SampleEvent',
            'role_key' => 'client',
            'tenant_id' => null,
            'is_active' => true,
            'personal_only' => false,
            'channels' => $channels,
            'options' => [],
        ]);
    }

    public function test_mapped_channel_is_included_when_routable(): void
    {
        $this->sub(['mail', 'telegram']);

        $user = SampleUser::withId(1);
        $user->email = 'test@example.com';
        $user->telegram_id = '42';

        $this->assertSame(['mail', 'telegram'], (new TelegramNotify('client'))->via($user));
    }

    public function test_mail_goes_to_an_on_demand_recipient(): void
    {
        $this->sub(['mail']);

        $notifiable = (new \Illuminate\Notifications\AnonymousNotifiable())->route('mail', 'guest@example.com');

        $this->assertSame(['mail'], (new TelegramNotify('client'))->via($notifiable));
    }

    public function test_mail_uses_route_notification_for_mail(): void
    {
        $this->sub(['mail']);

        $user = new class extends \Illuminate\Database\Eloquent\Model {
            use \Illuminate\Notifications\Notifiable;

            public function routeNotificationForMail(): string
            {
                return 'billing@example.com';
            }
        };

        $this->assertSame(['mail'], (new TelegramNotify('client'))->via($user));
    }

    public function test_mapped_channel_is_dropped_without_route(): void
    {
        $this->sub(['mail', 'telegram']);

        $user = SampleUser::withId(1);
        $user->email = 'test@example.com';

        $this->assertSame(['mail'], (new TelegramNotify('client'))->via($user));
    }

    public function test_opt_out_gate_applies_to_mapped_channels(): void
    {
        $this->sub(['telegram']);

        $user = SampleUser::withId(1);
        $user->telegram_id = '42';

        NotifyUserSetting::create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'notify_key' => 'SampleEvent',
            'is_enabled' => false,
        ]);

        $this->assertSame([], (new TelegramNotify('client'))->via($user));
    }

    public function test_except_applies_to_mapped_channels(): void
    {
        $this->sub(['mail', 'telegram']);

        $user = SampleUser::withId(1);
        $user->email = 'test@example.com';
        $user->telegram_id = '42';

        $this->assertSame(['mail'], (new TelegramNotify('client'))->except(['telegram'])->via($user));
    }
}
