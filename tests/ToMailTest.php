<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests;

use Fomvasss\NotifyTemplates\Models\NotifyTemplate;
use Fomvasss\NotifyTemplates\Tests\Fixtures\SampleNotify;
use Fomvasss\NotifyTemplates\Tests\Fixtures\SampleUser;

class ToMailTest extends TestCase
{
    public function test_paragraphs_and_line_breaks_are_kept_and_html_is_escaped(): void
    {
        NotifyTemplate::create([
            'notify_key' => 'SampleEvent',
            'channel' => 'mail',
            'role_key' => null,
            'tenant_id' => null,
            'subject' => 'Hi',
            'body' => "Hello,\nyour order is ready.\n\n<b>Thanks</b>",
        ]);

        $lines = array_map('strval', (new SampleNotify('client'))->toMail(SampleUser::withId(1))->introLines);

        $this->assertSame(["Hello,<br>\nyour order is ready.", '&lt;b&gt;Thanks&lt;/b&gt;'], $lines);
    }
}
