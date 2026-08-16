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
    ],

];
