<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

final class NotifiableUser extends Model
{
    use Notifiable;

    protected $table = 'sample_users';

    public static function withId(int $id, string $email): self
    {
        $model = new self();
        $model->id = $id;
        $model->email = $email;
        $model->exists = true;

        return $model;
    }
}
