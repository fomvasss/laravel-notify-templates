<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests\Fixtures;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Notification;

/**
 * Mimics third-party channels: `fail` throws, `swallow` dispatches NotificationFailed itself
 * and returns normally (as fomvasss/* channels do), otherwise returns a provider response.
 */
final class FakeSmsChannel
{
    public static string $mode = 'ok';

    public function __construct(private readonly Dispatcher $events) {}

    public function send(mixed $notifiable, Notification $notification): ?array
    {
        if (self::$mode === 'fail') {
            throw new \RuntimeException('Provider is down');
        }

        if (self::$mode === 'swallow') {
            $this->events->dispatch(new NotificationFailed($notifiable, $notification, self::class, ['message' => 'Invalid phone']));

            return null;
        }

        return ['message_id' => 'sms-1'];
    }
}
