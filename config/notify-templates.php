<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'notify_templates' => 'notify_templates',
        'notify_role_subscriptions' => 'notify_role_subscriptions',
        'notify_user_settings' => 'notify_user_settings',
        'notify_logs' => 'notify_logs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery channels available in the project.
    | Used for validation and UI listings.
    |--------------------------------------------------------------------------
    */
    'channels' => ['mail', 'telegram', 'sms', 'database', 'broadcast'],

    /*
    |--------------------------------------------------------------------------
    | Default channels used when subscription has no channels configured.
    | Also the guaranteed-delivery fallback in via(), but only for types with
    | 'user_configurable' => false (e.g. OTP) — for regular types an empty
    | resolution means "don't send" and is never overridden.
    |--------------------------------------------------------------------------
    */
    'default_channels' => ['mail'],

    /*
    |--------------------------------------------------------------------------
    | Tenant ID.
    | null = single-tenant. Set to a plain string or a callable returning one —
    | used as the fallback in resolveTemplate()/resolveChannels()/resolveDelay()
    | when no explicit $tenantId is passed.
    |--------------------------------------------------------------------------
    */
    'tenant_id' => null,

    /*
    |--------------------------------------------------------------------------
    | Pre-registered notify types (static approach).
    | Dynamic registration via NotifyTemplates::registerTypes() in ServiceProvider.
    |--------------------------------------------------------------------------
    | Each entry:
    |   key      string   unique notify identifier, e.g. 'OrderOrdered'
    |   name     string   human-readable label
    |   group    string   grouping key, e.g. 'order', 'user'
    |   settings array    which settings fields apply: ['delay']
    |   tokens   array    available token hints for the UI
    |--------------------------------------------------------------------------
    */
    'types' => [],

    /*
    |--------------------------------------------------------------------------
    | Auto-discovery paths.
    | Directories to scan for BaseNotify subclasses with typeDefinition().
    | Empty array disables auto-discovery.
    |--------------------------------------------------------------------------
    */
    'discover' => [
        app_path('Notifications'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model classes.
    | Override NotifyTemplate in your project to add e.g. astrotomic/translatable.
    |--------------------------------------------------------------------------
    */
    'models' => [
        'notify_template' => \Fomvasss\NotifyTemplates\Models\NotifyTemplate::class,
        'notify_role_subscription' => \Fomvasss\NotifyTemplates\Models\NotifyRoleSubscription::class,
        'notify_user_setting' => \Fomvasss\NotifyTemplates\Models\NotifyUserSetting::class,
        'notify_log' => \Fomvasss\NotifyTemplates\Models\NotifyLog::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery log (notify_logs).
    | One row per notification × channel × recipient, BaseNotify subclasses only.
    |--------------------------------------------------------------------------
    |   enabled — opt-in; needs the notify_logs migration
    |   retention_days — rows older than this are removed by `model:prune`
    |     (schedule it in the host app)
    |   external_id_resolvers — channel (as returned by via()) => class
    |     implementing ExternalIdResolverInterface: extracts the provider
    |     message id that NotifyTemplates::updateDelivery() matches by
    |--------------------------------------------------------------------------
    */
    'log' => [
        'enabled' => false,
        'retention_days' => 90,
        'external_id_resolvers' => [
            'mail' => \Fomvasss\NotifyTemplates\Resolvers\MailMessageIdResolver::class,
        ],
    ],

];
