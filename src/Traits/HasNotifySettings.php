<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Traits;

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
}
