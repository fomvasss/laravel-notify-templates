<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests;

use Fomvasss\NotifyTemplates\NotifyTemplatesManager;

class ManagerTest extends TestCase
{
    private NotifyTemplatesManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new NotifyTemplatesManager();
    }

    public function test_register_and_get_type(): void
    {
        $this->manager->registerType(['key' => 'OrderOrdered', 'name' => 'Замовлення', 'group' => 'order']);

        $type = $this->manager->getType('OrderOrdered');

        $this->assertNotNull($type);
        $this->assertSame('OrderOrdered', $type['key']);
    }

    public function test_get_type_returns_null_for_unknown_key(): void
    {
        $this->assertNull($this->manager->getType('Unknown'));
    }

    public function test_register_types_bulk(): void
    {
        $this->manager->registerTypes([
            ['key' => 'UserCreated', 'name' => 'Юзер', 'group' => 'user'],
            ['key' => 'UserOtp', 'name' => 'OTP', 'group' => 'user'],
        ]);

        $this->assertCount(2, $this->manager->getTypes());
    }

    public function test_get_types_filtered_by_group(): void
    {
        $this->manager->registerTypes([
            ['key' => 'UserCreated', 'name' => 'Юзер', 'group' => 'user'],
            ['key' => 'OrderOrdered', 'name' => 'Замовлення', 'group' => 'order'],
        ]);

        $userTypes = $this->manager->getTypes('user');

        $this->assertCount(1, $userTypes);
        $this->assertArrayHasKey('UserCreated', $userTypes);
    }

    public function test_register_type_overwrites_existing_key(): void
    {
        $this->manager->registerType(['key' => 'OrderOrdered', 'name' => 'Старе', 'group' => 'order']);
        $this->manager->registerType(['key' => 'OrderOrdered', 'name' => 'Нове', 'group' => 'order']);

        $this->assertSame('Нове', $this->manager->getType('OrderOrdered')['name']);
    }

    public function test_discover_in_finds_base_notify_subclass(): void
    {
        $this->manager->discoverIn(__DIR__ . '/Fixtures');

        $type = $this->manager->getType('SampleEvent');

        $this->assertNotNull($type);
        $this->assertSame('test', $type['group']);
    }

    public function test_discover_in_finds_nested_class(): void
    {
        $this->manager->discoverIn(__DIR__ . '/Fixtures');

        $this->assertNotNull($this->manager->getType('NestedEvent'));
    }

    public function test_discover_in_takes_the_key_from_notify_key_when_missing(): void
    {
        $this->manager->discoverIn(__DIR__ . '/DiscoveryFixtures/KeyFromClass');

        $this->assertSame('Order shipped', $this->manager->getType('OrderShipped')['name']);
    }

    public function test_discover_in_refuses_a_key_that_differs_from_notify_key(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('"SomethingElse" differs from notifyKey() "Mismatch"');

        $this->manager->discoverIn(__DIR__ . '/DiscoveryFixtures/Mismatch');
    }

    public function test_tenant_id_from_config_is_cast_and_only_class_method_strings_are_called(): void
    {
        config(['notify-templates.tenant_id' => fn () => 42]);
        $this->assertSame('42', $this->manager->resolveTenantId(null));

        config(['notify-templates.tenant_id' => 'date']);
        $this->assertSame('date', $this->manager->resolveTenantId(null));

        config(['notify-templates.tenant_id' => 7]);
        $this->assertSame('7', $this->manager->resolveTenantId(null));
    }

    public function test_an_empty_tenant_id_asks_for_global_rows(): void
    {
        config(['notify-templates.tenant_id' => 'shop-ua']);

        $this->assertNull($this->manager->resolveTenantId(''));
        $this->assertSame('shop-ua', $this->manager->resolveTenantId(null));
    }

    public function test_discover_in_ignores_nonexistent_path(): void
    {
        $this->manager->discoverIn('/tmp/nonexistent-notify-dir');

        $this->assertCount(0, $this->manager->getTypes());
    }

    public function test_register_type_throws_on_missing_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->registerType(['name' => 'No key here', 'group' => 'test']);
    }

    public function test_get_type_channels_from_definition(): void
    {
        $this->manager->registerType([
            'key' => 'OrderOrdered',
            'name' => 'Замовлення',
            'group' => 'order',
            'channels' => ['mail', 'sms'],
        ]);

        $this->assertSame(['mail', 'sms'], $this->manager->getTypeChannels('OrderOrdered'));
    }

    public function test_get_type_channels_falls_back_to_config(): void
    {
        $this->manager->registerType(['key' => 'OrderOrdered', 'name' => 'Замовлення', 'group' => 'order']);
        config(['notify-templates.channels' => ['mail', 'telegram']]);

        $this->assertSame(['mail', 'telegram'], $this->manager->getTypeChannels('OrderOrdered'));
    }

    public function test_get_channel_defaults(): void
    {
        config(['notify-templates.channel_options' => []]);

        $this->assertSame(['label' => 'Mail', 'slot' => 'mail', 'key' => 'mail'], $this->manager->getChannel('mail'));
        $this->assertSame(['label' => 'Viber', 'slot' => 'messenger', 'key' => 'viber'], $this->manager->getChannel('viber'));
    }

    public function test_get_channel_merges_options(): void
    {
        config(['notify-templates.channel_options' => [
            'sms' => ['label' => 'SMS', 'slot' => 'sms', 'icon' => 'fas fa-sms', 'key' => 'ignored'],
        ]]);

        $this->assertSame(
            ['label' => 'SMS', 'slot' => 'sms', 'icon' => 'fas fa-sms', 'key' => 'sms'],
            $this->manager->getChannel('sms'),
        );
    }

    public function test_get_channels_keyed_by_slug_in_config_order(): void
    {
        config([
            'notify-templates.channels' => ['telegram', 'mail'],
            'notify-templates.channel_options' => ['telegram' => ['icon' => 'fab fa-telegram']],
        ]);

        $channels = $this->manager->getChannels();

        $this->assertSame(['telegram', 'mail'], array_keys($channels));
        $this->assertSame('fab fa-telegram', $channels['telegram']['icon']);
        $this->assertSame('messenger', $channels['telegram']['slot']);
    }

    public function test_get_slot_defaults(): void
    {
        config(['notify-templates.slot_options' => []]);

        $this->assertSame(['label' => 'Mail', 'subject' => true, 'key' => 'mail'], $this->manager->getSlot('mail'));
        $this->assertSame(['label' => 'Messenger', 'subject' => false, 'key' => 'messenger'], $this->manager->getSlot('messenger'));
    }

    public function test_get_slot_merges_options(): void
    {
        config(['notify-templates.slot_options' => ['sms' => ['label' => 'SMS', 'max_length' => 160]]]);

        $this->assertSame(['label' => 'SMS', 'subject' => false, 'max_length' => 160, 'key' => 'sms'], $this->manager->getSlot('sms'));
    }

    public function test_get_slots_distinct_from_channels(): void
    {
        config([
            'notify-templates.channels' => ['telegram', 'mail', 'viber'],
            'notify-templates.channel_options' => [],
            'notify-templates.slot_options' => ['messenger' => ['label' => 'Месенджер']],
        ]);

        $slots = $this->manager->getSlots();

        $this->assertSame(['messenger', 'mail'], array_keys($slots));
        $this->assertSame('Месенджер', $slots['messenger']['label']);
    }

    public function test_get_type_slots_distinct_in_channel_order(): void
    {
        config(['notify-templates.channel_options' => ['sms' => ['slot' => 'sms']]]);
        $this->manager->registerType([
            'key' => 'OrderOrdered',
            'name' => 'Замовлення',
            'group' => 'order',
            'channels' => ['telegram', 'mail', 'viber', 'sms'],
        ]);

        $this->assertSame(['messenger', 'mail', 'sms'], $this->manager->getTypeSlots('OrderOrdered'));
    }
}
