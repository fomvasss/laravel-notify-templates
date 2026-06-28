<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests;

use Fomvasss\NotifyTemplates\Models\NotifyTemplate;

class ResolveTemplateTest extends TestCase
{
    private function make(array $attrs): NotifyTemplate
    {
        return NotifyTemplate::create(array_merge([
            'notify_key' => 'OrderOrdered',
            'channel' => null,
            'role_key' => null,
            'tenant_id' => null,
            'body' => 'default body',
        ], $attrs));
    }

    public function test_returns_null_when_no_template(): void
    {
        $result = NotifyTemplate::resolve('OrderOrdered', 'mail');

        $this->assertNull($result);
    }

    public function test_returns_global_fallback(): void
    {
        $this->make(['body' => 'global']);

        $result = NotifyTemplate::resolve('OrderOrdered', 'mail');

        $this->assertSame('global', $result->body);
    }

    public function test_channel_specific_wins_over_global(): void
    {
        $this->make(['body' => 'global']);
        $this->make(['channel' => 'mail', 'body' => 'mail specific']);

        $result = NotifyTemplate::resolve('OrderOrdered', 'mail');

        $this->assertSame('mail specific', $result->body);
    }

    public function test_role_specific_wins_over_global(): void
    {
        $this->make(['body' => 'global']);
        $this->make(['channel' => 'mail', 'role_key' => 'client', 'body' => 'client mail']);

        $result = NotifyTemplate::resolve('OrderOrdered', 'mail', 'client');

        $this->assertSame('client mail', $result->body);
    }

    public function test_tenant_specific_wins_over_global(): void
    {
        $this->make(['channel' => 'mail', 'body' => 'global mail']);
        $this->make(['channel' => 'mail', 'tenant_id' => 'shop-ua', 'body' => 'tenant mail']);

        $result = NotifyTemplate::resolve('OrderOrdered', 'mail', null, 'shop-ua');

        $this->assertSame('tenant mail', $result->body);
    }

    public function test_most_specific_wins(): void
    {
        $this->make(['body' => 'global']);
        $this->make(['channel' => 'mail', 'body' => 'mail']);
        $this->make(['channel' => 'mail', 'role_key' => 'client', 'body' => 'mail+client']);
        $this->make(['channel' => 'mail', 'role_key' => 'client', 'tenant_id' => 'shop-ua', 'body' => 'mail+client+tenant']);

        $result = NotifyTemplate::resolve('OrderOrdered', 'mail', 'client', 'shop-ua');

        $this->assertSame('mail+client+tenant', $result->body);
    }

    public function test_falls_back_to_null_channel_when_no_channel_match(): void
    {
        $this->make(['channel' => null, 'role_key' => 'client', 'body' => 'null-channel+client']);

        $result = NotifyTemplate::resolve('OrderOrdered', 'mail', 'client');

        $this->assertSame('null-channel+client', $result->body);
    }

    public function test_role_null_template_not_returned_for_specific_role_query(): void
    {
        // role=null template should still be returned as fallback when no role-specific one exists
        $this->make(['channel' => 'mail', 'role_key' => null, 'body' => 'no-role']);

        $result = NotifyTemplate::resolve('OrderOrdered', 'mail', 'client');

        $this->assertSame('no-role', $result->body);
    }
}
