<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Stand-in "notifiable" — isNotifyEnabled() only ever reads getMorphClass()/getKey()
 * off it, so it never needs a backing table of its own.
 */
final class SampleUser extends Model
{
    protected $table = 'sample_users';

    public ?array $notifyChannels = null;

    public static function withId(int|string $id): self
    {
        $model = new self();
        $model->id = $id;
        $model->exists = true;

        return $model;
    }

    public function getNotifyChannels(): array
    {
        return $this->notifyChannels ?? [];
    }
}
