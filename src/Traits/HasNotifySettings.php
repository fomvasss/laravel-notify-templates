<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Traits;

use Fomvasss\NotifyTemplates\Facades\NotifyTemplates;

trait HasNotifySettings
{
    /**
     * Delivery channels enabled for this user.
     * Expects a 'notify_channels' cast-to-array column, or override this method.
     */
    public function getNotifyChannels(): array
    {
        return $this->notify_channels ?? [];
    }

    /**
     * Whether this notifiable personally opted out of a notify type (notify_user_settings).
     * BaseNotify::via() already checks this on every send without requiring the trait —
     * this is a convenience for querying the same state outside a Notification (e.g. a profile form).
     */
    public function isNotifyEnabled(string $notifyKey): bool
    {
        return NotifyTemplates::isNotifyEnabled($notifyKey, $this);
    }
}
