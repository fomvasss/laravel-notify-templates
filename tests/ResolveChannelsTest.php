<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests;

use Fomvasss\NotifyTemplates\Models\NotifyRoleSubscription;
use Fomvasss\NotifyTemplates\NotifyTemplatesManager;

class ResolveChannelsTest extends TestCase
{
    private NotifyTemplatesManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(NotifyTemplatesManager::class);
    }

    private function sub(array $attrs): NotifyRoleSubscription
    {
        return NotifyRoleSubscription::create(array_merge([
            'notify_key' => 'OrderOrdered',
            'role_key' => 'client',
            'tenant_id' => null,
            'is_active' => true,
            'personal_only' => false,
            'channels' => ['mail'],
            'options' => [],
        ], $attrs));
    }

    // --- resolveChannels ---

    public function test_returns_empty_when_no_subscription(): void
    {
        $result = $this->manager->resolveChannels('OrderOrdered', 'client');

        $this->assertSame([], $result);
    }

    public function test_returns_default_channels_when_subscription_has_no_channels(): void
    {
        $this->sub(['channels' => []]);
        config(['notify-templates.default_channels' => ['mail', 'sms']]);

        $result = $this->manager->resolveChannels('OrderOrdered', 'client');

        $this->assertSame(['mail', 'sms'], $result);
    }

    public function test_returns_empty_when_subscription_inactive(): void
    {
        $this->sub(['is_active' => false]);

        $result = $this->manager->resolveChannels('OrderOrdered', 'client');

        $this->assertSame([], $result);
    }

    public function test_returns_channels_when_active(): void
    {
        $this->sub(['channels' => ['mail', 'telegram']]);

        $result = $this->manager->resolveChannels('OrderOrdered', 'client');

        $this->assertSame(['mail', 'telegram'], $result);
    }

    public function test_user_channels_filter_subscription_channels(): void
    {
        $this->sub(['channels' => ['mail', 'sms']]);

        $result = $this->manager->resolveChannels('OrderOrdered', 'client', null, ['mail']);

        $this->assertSame(['mail'], $result);
    }

    public function test_user_channels_not_in_subscription_are_excluded(): void
    {
        $this->sub(['channels' => ['mail']]);

        $result = $this->manager->resolveChannels('OrderOrdered', 'client', null, ['telegram']);

        $this->assertSame([], $result);
    }

    public function test_returns_all_subscription_channels_when_no_user_preferences(): void
    {
        $this->sub(['channels' => ['mail', 'sms']]);

        $result = $this->manager->resolveChannels('OrderOrdered', 'client');

        $this->assertSame(['mail', 'sms'], $result);
    }

    // --- resolveDelay ---

    public function test_resolve_delay_returns_zero_when_no_subscription(): void
    {
        $this->assertSame(0, $this->manager->resolveDelay('OrderOrdered', 'client'));
    }

    public function test_resolve_delay_returns_seconds(): void
    {
        $this->sub(['options' => ['delay' => 5]]);

        $this->assertSame(300, $this->manager->resolveDelay('OrderOrdered', 'client'));
    }

    public function test_resolve_delay_returns_zero_when_no_delay_option(): void
    {
        $this->sub(['options' => []]);

        $this->assertSame(0, $this->manager->resolveDelay('OrderOrdered', 'client'));
    }

    // --- NotifyRoleSubscription::resolve() tenant preference ---

    public function test_tenant_specific_subscription_preferred_over_global(): void
    {
        $this->sub(['channels' => ['mail'], 'tenant_id' => null]);
        $this->sub(['channels' => ['mail', 'sms'], 'tenant_id' => 'shop-ua']);

        $result = $this->manager->resolveChannels('OrderOrdered', 'client', 'shop-ua');

        $this->assertContains('sms', $result);
    }

    public function test_global_subscription_used_when_no_tenant_specific(): void
    {
        $this->sub(['channels' => ['mail'], 'tenant_id' => null]);

        $result = $this->manager->resolveChannels('OrderOrdered', 'client', 'shop-ua');

        $this->assertSame(['mail'], $result);
    }

    // --- personal_only ---

    public function test_personal_only_stored_and_retrieved(): void
    {
        $this->sub(['personal_only' => true]);

        $sub = NotifyRoleSubscription::resolve('client', 'OrderOrdered');

        $this->assertTrue($sub->personal_only);
    }
}
