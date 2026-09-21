<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests;

use Fomvasss\NotifyTemplates\Models\NotifyLog;
use Fomvasss\NotifyTemplates\NotifyTemplatesManager;
use Fomvasss\NotifyTemplates\Tests\Fixtures\FakeSmsChannel;
use Fomvasss\NotifyTemplates\Tests\Fixtures\FakeSmsIdResolver;
use Fomvasss\NotifyTemplates\Tests\Fixtures\NotifiableUser;
use Fomvasss\NotifyTemplates\Tests\Fixtures\SampleNotify;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

class NotifyLogTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('mail.default', 'array');
        $app['config']->set('notify-templates.log.enabled', true);
        $app['config']->set('notify-templates.log.external_id_resolvers.'.FakeSmsChannel::class, FakeSmsIdResolver::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        FakeSmsChannel::$mode = 'ok';
    }

    private function send(mixed $notifiable, ?SampleNotify $notify = null, string $channel = 'mail'): void
    {
        Notification::sendNow($notifiable, $notify ?? new SampleNotify('client'), [$channel]);
    }

    private function sendSms(): NotifyLog
    {
        $this->send(NotifiableUser::withId(1, 'a@example.com'), channel: FakeSmsChannel::class);

        return NotifyLog::sole();
    }

    public function test_sent_mail_is_logged_with_recipient_and_message_id(): void
    {
        $this->send(NotifiableUser::withId(7, 'a@example.com'));

        $log = NotifyLog::sole();

        $this->assertSame(NotifyLog::STATUS_SENT, $log->status);
        $this->assertSame('SampleEvent', $log->notify_key);
        $this->assertSame('mail', $log->channel);
        $this->assertSame('client', $log->role_key);
        $this->assertSame('7', (string) $log->notifiable_id);
        $this->assertSame('a@example.com', $log->route);
        $this->assertNotEmpty($log->external_id);
        $this->assertSame(1, $log->attempts);
    }

    public function test_on_demand_recipient_is_logged_by_route(): void
    {
        $this->send((new AnonymousNotifiable())->route('mail', 'guest@example.com'));

        $log = NotifyLog::sole();

        $this->assertNull($log->notifiable_type);
        $this->assertSame('guest@example.com', $log->route);
        $this->assertSame(NotifyLog::STATUS_SENT, $log->status);
    }

    public function test_external_id_comes_from_configured_resolver(): void
    {
        $this->assertSame('sms-1', $this->sendSms()->external_id);
    }

    public function test_channel_exception_marks_failed(): void
    {
        FakeSmsChannel::$mode = 'fail';

        try {
            $this->sendSms();
        } catch (\RuntimeException) {
        }

        $log = NotifyLog::sole();

        $this->assertSame(NotifyLog::STATUS_FAILED, $log->status);
        $this->assertSame('Provider is down', $log->error);
    }

    public function test_swallowed_failure_is_not_overwritten_by_sent(): void
    {
        FakeSmsChannel::$mode = 'swallow';

        $log = $this->sendSms();

        $this->assertSame(NotifyLog::STATUS_FAILED, $log->status);
        $this->assertSame('Invalid phone', $log->error);
    }

    public function test_retry_of_same_notification_reuses_row(): void
    {
        $user = NotifiableUser::withId(1, 'a@example.com');
        $notify = new SampleNotify('client');
        $notify->id = 'retry-id';

        FakeSmsChannel::$mode = 'fail';
        try {
            $this->send($user, $notify, FakeSmsChannel::class);
        } catch (\RuntimeException) {
        }

        FakeSmsChannel::$mode = 'ok';
        $this->send($user, $notify, FakeSmsChannel::class);

        $log = NotifyLog::sole();

        $this->assertSame(NotifyLog::STATUS_SENT, $log->status);
        $this->assertSame(2, $log->attempts);
        $this->assertNull($log->error);
    }

    public function test_update_delivery_moves_status_forward_only(): void
    {
        $log = $this->sendSms();
        $manager = app(NotifyTemplatesManager::class);

        $this->assertTrue($manager->updateDelivery(FakeSmsChannel::class, 'sms-1', NotifyLog::STATUS_READ, ['raw' => 'read']));
        $this->assertFalse($manager->updateDelivery(FakeSmsChannel::class, 'sms-1', NotifyLog::STATUS_DELIVERED));
        $this->assertFalse($manager->updateDelivery(FakeSmsChannel::class, 'sms-1', NotifyLog::STATUS_FAILED));

        $log->refresh();
        $this->assertSame(NotifyLog::STATUS_READ, $log->status);
        $this->assertSame(['raw' => 'read'], $log->payload);
    }

    public function test_update_delivery_fails_accepted_message(): void
    {
        $this->sendSms();

        $this->assertTrue(app(NotifyTemplatesManager::class)->updateDelivery(FakeSmsChannel::class, 'sms-1', NotifyLog::STATUS_FAILED));
        $this->assertSame(NotifyLog::STATUS_FAILED, NotifyLog::sole()->status);
    }

    public function test_update_delivery_unknown_id_is_noop(): void
    {
        $this->assertFalse(app(NotifyTemplatesManager::class)->updateDelivery('mail', 'nope', NotifyLog::STATUS_DELIVERED));
    }

    public function test_update_delivery_rejects_invalid_status(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(NotifyTemplatesManager::class)->updateDelivery('mail', 'x', NotifyLog::STATUS_PENDING);
    }

    public function test_prune_removes_rows_past_retention(): void
    {
        $this->sendSms();
        NotifyLog::query()->update(['created_at' => now()->subDays(91)]);

        $this->assertSame(1, (new NotifyLog())->pruneAll());
        $this->assertSame(0, NotifyLog::count());
    }
}
