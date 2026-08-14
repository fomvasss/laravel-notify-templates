<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests;

use Fomvasss\NotifyTemplates\Facades\NotifyTemplates;
use Fomvasss\NotifyTemplates\Models\NotifyRoleSubscription;
use Fomvasss\NotifyTemplates\Models\NotifyUserSetting;
use Fomvasss\NotifyTemplates\NotifyTemplatesManager;
use Fomvasss\NotifyTemplates\Tests\Fixtures\SampleNotify;
use Fomvasss\NotifyTemplates\Tests\Fixtures\SampleUser;

class NotifyUserSettingTest extends TestCase
{
    private NotifyTemplatesManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(NotifyTemplatesManager::class);
    }

    public function test_enabled_by_default_when_no_row(): void
    {
        $user = SampleUser::withId(1);

        $this->assertTrue($this->manager->isNotifyEnabled('OrderOrdered', $user));
    }

    public function test_disabled_row_is_respected(): void
    {
        $user = SampleUser::withId(1);

        NotifyUserSetting::create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'notify_key' => 'OrderOrdered',
            'is_enabled' => false,
        ]);

        $this->assertFalse($this->manager->isNotifyEnabled('OrderOrdered', $user));
    }

    public function test_enabled_row_is_respected(): void
    {
        $user = SampleUser::withId(1);

        NotifyUserSetting::create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'notify_key' => 'OrderOrdered',
            'is_enabled' => true,
        ]);

        $this->assertTrue($this->manager->isNotifyEnabled('OrderOrdered', $user));
    }

    public function test_scoped_by_notify_key(): void
    {
        $user = SampleUser::withId(1);

        NotifyUserSetting::create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'notify_key' => 'OrderOrdered',
            'is_enabled' => false,
        ]);

        $this->assertTrue($this->manager->isNotifyEnabled('UserRegistered', $user));
    }

    public function test_scoped_by_notifiable_id(): void
    {
        $userOne = SampleUser::withId(1);
        $userTwo = SampleUser::withId(2);

        NotifyUserSetting::create([
            'notifiable_type' => $userOne->getMorphClass(),
            'notifiable_id' => $userOne->getKey(),
            'notify_key' => 'OrderOrdered',
            'is_enabled' => false,
        ]);

        $this->assertTrue($this->manager->isNotifyEnabled('OrderOrdered', $userTwo));
    }

    public function test_non_model_notifiable_defaults_to_enabled(): void
    {
        $this->assertTrue($this->manager->isNotifyEnabled('OrderOrdered', null));
    }

    public function test_accessible_via_facade(): void
    {
        $user = SampleUser::withId(1);

        $this->assertTrue(NotifyTemplates::isNotifyEnabled('OrderOrdered', $user));
    }

    // --- integration: BaseNotify::via() gate ---

    public function test_via_returns_empty_when_notifiable_opted_out(): void
    {
        $user = SampleUser::withId(1);
        $user->email = 'test@example.com';

        NotifyRoleSubscription::create([
            'notify_key' => 'SampleEvent',
            'role_key' => 'client',
            'tenant_id' => null,
            'is_active' => true,
            'personal_only' => false,
            'channels' => ['mail'],
            'options' => [],
        ]);

        NotifyUserSetting::create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'notify_key' => 'SampleEvent',
            'is_enabled' => false,
        ]);

        $result = (new SampleNotify('client'))->via($user);

        $this->assertSame([], $result);
    }

    public function test_via_sends_when_notifiable_has_no_opt_out_row(): void
    {
        $user = SampleUser::withId(1);
        $user->email = 'test@example.com';

        NotifyRoleSubscription::create([
            'notify_key' => 'SampleEvent',
            'role_key' => 'client',
            'tenant_id' => null,
            'is_active' => true,
            'personal_only' => false,
            'channels' => ['mail'],
            'options' => [],
        ]);

        $result = (new SampleNotify('client'))->via($user);

        $this->assertContains('mail', $result);
    }

    // --- resolveNotifyUserChannels / per-type channel override ---

    public function test_channels_override_null_when_no_row(): void
    {
        $user = SampleUser::withId(1);

        $this->assertNull($this->manager->resolveNotifyUserChannels('OrderOrdered', $user));
    }

    public function test_channels_override_returned_when_set(): void
    {
        $user = SampleUser::withId(1);

        NotifyUserSetting::create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'notify_key' => 'OrderOrdered',
            'channels' => ['telegram'],
        ]);

        $this->assertSame(['telegram'], $this->manager->resolveNotifyUserChannels('OrderOrdered', $user));
    }

    public function test_via_restricts_to_override_channel(): void
    {
        $user = SampleUser::withId(1);
        $user->email = 'test@example.com';

        NotifyRoleSubscription::create([
            'notify_key' => 'SampleEvent',
            'role_key' => 'client',
            'tenant_id' => null,
            'is_active' => true,
            'personal_only' => false,
            'channels' => ['mail', 'database'],
            'options' => [],
        ]);

        NotifyUserSetting::create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'notify_key' => 'SampleEvent',
            'channels' => ['database'],
        ]);

        $result = (new SampleNotify('client'))->via($user);

        $this->assertSame(['database'], $result);
    }

    public function test_via_override_intersects_with_notifiable_global_channels(): void
    {
        $user = SampleUser::withId(1);
        $user->email = 'test@example.com';

        NotifyRoleSubscription::create([
            'notify_key' => 'SampleEvent',
            'role_key' => 'client',
            'tenant_id' => null,
            'is_active' => true,
            'personal_only' => false,
            'channels' => ['mail', 'database'],
            'options' => [],
        ]);

        // Global preference already excludes 'database' — override can only narrow, not widen it.
        NotifyUserSetting::create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'notify_key' => 'SampleEvent',
            'channels' => ['mail', 'database'],
        ]);

        $user->notifyChannels = ['mail'];

        $result = (new SampleNotify('client'))->via($user);

        $this->assertSame(['mail'], $result);
    }

    // --- user_configurable: false (e.g. OTP) — opt-out/channel-override never apply ---

    public function test_is_user_configurable_defaults_to_true(): void
    {
        $this->assertTrue($this->manager->isUserConfigurable('UnregisteredType'));
    }

    public function test_is_user_configurable_false_when_type_opts_out(): void
    {
        $this->manager->registerType(['key' => 'UserOtp', 'name' => 'OTP', 'group' => 'user', 'user_configurable' => false]);

        $this->assertFalse($this->manager->isUserConfigurable('UserOtp'));
    }

    public function test_is_notify_enabled_ignores_opt_out_row_when_not_configurable(): void
    {
        $this->manager->registerType(['key' => 'UserOtp', 'name' => 'OTP', 'group' => 'user', 'user_configurable' => false]);
        $user = SampleUser::withId(1);

        NotifyUserSetting::create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'notify_key' => 'UserOtp',
            'is_enabled' => false,
        ]);

        $this->assertTrue($this->manager->isNotifyEnabled('UserOtp', $user));
    }

    public function test_resolve_notify_user_channels_ignores_override_when_not_configurable(): void
    {
        $this->manager->registerType(['key' => 'UserOtp', 'name' => 'OTP', 'group' => 'user', 'user_configurable' => false]);
        $user = SampleUser::withId(1);

        NotifyUserSetting::create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'notify_key' => 'UserOtp',
            'channels' => ['telegram'],
        ]);

        $this->assertNull($this->manager->resolveNotifyUserChannels('UserOtp', $user));
    }
}
