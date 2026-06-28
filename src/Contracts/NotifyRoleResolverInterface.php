<?php

declare(strict_types=1);

namespace Fomvasss\NotifyTemplates\Contracts;

interface NotifyRoleResolverInterface
{
    /**
     * Return users grouped by role key for the given notify type.
     *
     * $context is the domain model relevant to this notify (Order, User, Lead, etc.).
     * The implementation uses NotifyRoleSubscription scopes + its own user-lookup
     * (Spatie, custom roles, etc.).
     *
     * Return shape: ['role_key' => iterable<Notifiable>]
     *
     * Example implementation (Spatie):
     *
     *   $subscriptions = NotifyRoleSubscription::active()->forNotify($notifyKey)->forTenant($tenantId)->get();
     *   foreach ($subscriptions as $sub) {
     *       if ($sub->personal_only && $context?->user) {
     *           $result[$sub->role_key] = collect([$context->user]);
     *       } else {
     *           $result[$sub->role_key] = User::role($sub->role_key)->active()->get();
     *       }
     *   }
     */
    public function resolveUsersForNotify(string $notifyKey, mixed $context = null): array;
}
