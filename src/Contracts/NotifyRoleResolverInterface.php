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
     * Example implementation (Spatie). Note the role whitelist: only roles you actually trust to
     * receive a broadcast (an internal staff role, typically) fall through to "all role holders".
     * Any other role — including any role added later that you forget to review here — defaults
     * to personal-only delivery. The naive `if ($sub->personal_only && $context?->user)` without
     * this whitelist is a real footgun: a role subscription created with its default
     * personal_only=false (e.g. via a lazy firstOrNew() the first time someone opens the admin
     * UI for a new notify type) silently broadcasts to *every* holder of that role — which, for
     * a "customer" / "tenant" role with many unrelated accounts, means leaking one person's
     * event (an invite link, a generated password, ...) to everyone else on the platform:
     *
     *   const BROADCAST_SAFE_ROLES = ['admin']; // roles safe to broadcast to when personal_only=false
     *
     *   $subscriptions = NotifyRoleSubscription::active()->forNotify($notifyKey)->forTenant($tenantId)->get();
     *   foreach ($subscriptions as $sub) {
     *       $forcePersonal = !in_array($sub->role_key, self::BROADCAST_SAFE_ROLES, true);
     *       if (($sub->personal_only || $forcePersonal) && $context?->user) {
     *           $result[$sub->role_key] = collect([$context->user]);
     *       } else {
     *           $result[$sub->role_key] = User::role($sub->role_key)->active()->get();
     *       }
     *   }
     */
    public function resolveUsersForNotify(string $notifyKey, mixed $context = null): array;
}
