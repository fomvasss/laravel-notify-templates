<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests;

use Fomvasss\NotifyTemplates\Models\NotifyRoleSubscription;
use Fomvasss\NotifyTemplates\Tests\Fixtures\SampleNotify;

class OnlyExceptTest extends TestCase
{
    private object $notifiable;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notifiable = (object)['email' => 'test@example.com'];

        NotifyRoleSubscription::create([
            'notify_key' => 'SampleEvent',
            'role_key' => 'client',
            'tenant_id' => null,
            'is_active' => true,
            'personal_only' => false,
            'channels' => ['mail', 'database'],
            'options' => [],
        ]);
    }

    public function test_only_restricts_channels(): void
    {
        $result = (new SampleNotify('client'))->only(['mail'])->via($this->notifiable);

        $this->assertSame(['mail'], $result);
    }

    public function test_except_excludes_channel(): void
    {
        $result = (new SampleNotify('client'))->except(['database'])->via($this->notifiable);

        $this->assertSame(['mail'], $result);
    }

    public function test_only_with_non_existing_channel_returns_empty(): void
    {
        $result = (new SampleNotify('client'))->only(['telegram'])->via($this->notifiable);

        $this->assertSame([], $result);
    }

    public function test_without_only_except_returns_all_resolved_channels(): void
    {
        $result = (new SampleNotify('client'))->via($this->notifiable);

        $this->assertContains('mail', $result);
        $this->assertContains('database', $result);
    }
}
