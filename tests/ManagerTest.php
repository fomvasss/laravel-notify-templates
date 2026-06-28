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
}
