<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests;

use Fomvasss\NotifyTemplates\Models\NotifyRoleSubscription;
use Fomvasss\NotifyTemplates\NotifyTemplatesManager;
use Fomvasss\NotifyTemplates\Tests\Fixtures\SampleNotify;
use Fomvasss\NotifyTemplates\Tests\Fixtures\SampleUser;

/**
 * default_channels is a guaranteed-delivery fallback ONLY for user_configurable=false types;
 * for regular types an empty resolution means "don't send".
 */
class ViaFallbackTest extends TestCase
{
    public function test_via_empty_when_no_subscription(): void
    {
        $user = SampleUser::withId(1);
        $user->email = 'test@example.com';

        $this->assertSame([], (new SampleNotify('client'))->via($user));
    }

    public function test_via_empty_when_subscription_inactive(): void
    {
        $user = SampleUser::withId(1);
        $user->email = 'test@example.com';

        NotifyRoleSubscription::create([
            'notify_key' => 'SampleEvent',
            'role_key' => 'client',
            'tenant_id' => null,
            'is_active' => false,
            'personal_only' => false,
            'channels' => ['mail'],
            'options' => [],
        ]);

        $this->assertSame([], (new SampleNotify('client'))->via($user));
    }

    public function test_via_empty_when_user_global_channels_exclude_subscription_channels(): void
    {
        $user = SampleUser::withId(1);
        $user->email = 'test@example.com';
        $user->notifyChannels = ['telegram'];

        NotifyRoleSubscription::create([
            'notify_key' => 'SampleEvent',
            'role_key' => 'client',
            'tenant_id' => null,
            'is_active' => true,
            'personal_only' => false,
            'channels' => ['mail'],
            'options' => [],
        ]);

        $this->assertSame([], (new SampleNotify('client'))->via($user));
    }

    public function test_via_falls_back_to_default_channels_for_non_configurable_type(): void
    {
        app(NotifyTemplatesManager::class)->registerType([
            'key' => 'SampleEvent',
            'name' => 'Sample event',
            'group' => 'test',
            'user_configurable' => false,
        ]);

        $user = SampleUser::withId(1);
        $user->email = 'test@example.com';

        $this->assertSame(['mail'], (new SampleNotify('client'))->via($user));
    }
}
