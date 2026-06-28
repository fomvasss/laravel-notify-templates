<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Facades;

use Fomvasss\NotifyTemplates\NotifyTemplatesManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void registerType(array $type)
 * @method static void registerTypes(array $types)
 * @method static void discoverIn(string $path)
 * @method static array getTypes(?string $group = null)
 * @method static array|null getType(string $key)
 * @method static \Fomvasss\NotifyTemplates\Models\NotifyTemplate|null resolveTemplate(string $notifyKey, string $channel, ?string $roleKey = null, ?string $tenantId = null)
 * @method static array resolveChannels(string $notifyKey, string $roleKey, ?string $tenantId = null, array $userChannels = [])
 * @method static int resolveDelay(string $notifyKey, string $roleKey, ?string $tenantId = null)
 *
 * @see NotifyTemplatesManager
 */
class NotifyTemplates extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NotifyTemplatesManager::class;
    }
}
