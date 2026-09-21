<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Listeners;

use Fomvasss\NotifyTemplates\Contracts\ExternalIdResolverInterface;
use Fomvasss\NotifyTemplates\Models\NotifyLog;
use Fomvasss\NotifyTemplates\Notifications\BaseNotify;
use Fomvasss\NotifyTemplates\NotifyTemplatesManager;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Writes one notify_logs row per notification × channel × notifiable. The row is created on
 * NotificationSending (so a send that dies without any further event still leaves a `pending`
 * trace) and a queue retry of the same notification reuses it, bumping `attempts`.
 */
class NotifyLogSubscriber
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            NotificationSending::class => 'handleSending',
            NotificationSent::class => 'handleSent',
            NotificationFailed::class => 'handleFailed',
        ];
    }

    public function handleSending(NotificationSending $event): void
    {
        if (!$event->notification instanceof BaseNotify) {
            return;
        }

        $log = $this->find($event->notifiable, $event->notification, $event->channel);

        if ($log) {
            $log->update([
                'status' => NotifyLog::STATUS_PENDING,
                'error' => null,
                'attempts' => $log->attempts + 1,
                'status_updated_at' => now(),
            ]);

            return;
        }

        $notifiable = $event->notifiable instanceof Model ? $event->notifiable : null;

        $this->model()::create([
            'notification_id' => $event->notification->id,
            'notify_key' => $event->notification->getNotifyKey(),
            'channel' => $event->channel,
            'role_key' => $event->notification->getRoleKey(),
            'tenant_id' => app(NotifyTemplatesManager::class)->resolveTenantId($event->notification->getTenantId()),
            'notifiable_type' => $notifiable?->getMorphClass(),
            'notifiable_id' => $notifiable?->getKey(),
            'route' => $this->route($event->notifiable, $event->notification, $event->channel),
            'status' => NotifyLog::STATUS_PENDING,
            'status_updated_at' => now(),
        ]);
    }

    public function handleSent(NotificationSent $event): void
    {
        if (!$event->notification instanceof BaseNotify) {
            return;
        }

        $log = $this->find($event->notifiable, $event->notification, $event->channel);

        // Channels that swallow their own exception dispatch NotificationFailed and return
        // normally, so Laravel still fires NotificationSent right after — keep the failure.
        if (!$log || $log->status === NotifyLog::STATUS_FAILED) {
            return;
        }

        $log->update([
            'status' => NotifyLog::STATUS_SENT,
            'external_id' => $this->externalId($event->channel, $event->response),
            'status_updated_at' => now(),
        ]);
    }

    public function handleFailed(NotificationFailed $event): void
    {
        if (!$event->notification instanceof BaseNotify) {
            return;
        }

        $log = $this->find($event->notifiable, $event->notification, $event->channel);

        if (!$log) {
            return;
        }

        $exception = $event->data['exception'] ?? null;

        $log->update([
            'status' => NotifyLog::STATUS_FAILED,
            'error' => $exception instanceof \Throwable
                ? $exception->getMessage()
                : ($event->data['message'] ?? null),
            'payload' => array_filter($event->data, fn($value) => !is_object($value)) ?: null,
            'status_updated_at' => now(),
        ]);
    }

    private function find(mixed $notifiable, BaseNotify $notification, string $channel): ?NotifyLog
    {
        $notifiable = $notifiable instanceof Model ? $notifiable : null;

        return $this->model()::query()
            ->where('notification_id', $notification->id)
            ->where('channel', $channel)
            ->where('notifiable_type', $notifiable?->getMorphClass())
            ->where('notifiable_id', $notifiable?->getKey())
            ->first();
    }

    private function route(mixed $notifiable, BaseNotify $notification, string $channel): ?string
    {
        if (!method_exists($notifiable, 'routeNotificationFor')) {
            return null;
        }

        $route = $notifiable->routeNotificationFor($channel, $notification);

        return match (true) {
            is_scalar($route) => mb_substr((string) $route, 0, 255),
            is_array($route) => mb_substr(implode(', ', array_map(
                fn($key, $value) => is_string($key) ? $key : (string) $value,
                array_keys($route),
                $route,
            )), 0, 255),
            default => null,
        };
    }

    private function externalId(string $channel, mixed $response): ?string
    {
        // Not a dotted config() lookup — channel may be a class-string or contain dots
        $class = config('notify-templates.log.external_id_resolvers', [])[$channel] ?? null;

        if (!$class) {
            return null;
        }

        /** @var ExternalIdResolverInterface $resolver */
        $resolver = app($class);

        return $resolver->resolve($response);
    }

    /** @return class-string<NotifyLog> */
    private function model(): string
    {
        return config('notify-templates.models.notify_log', NotifyLog::class);
    }
}
