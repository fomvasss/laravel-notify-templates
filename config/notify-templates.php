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
    | Default channels used when subscription has no channels configured,
    | or when via() resolves to nothing entirely.
    |--------------------------------------------------------------------------
    */
    'default_channels' => ['mail'],

    /*
    |--------------------------------------------------------------------------
    | Tenant ID resolver.
    | null = single-tenant. Set to a callable that returns the tenant ID string,
    | or bind NotifyTenantResolverInterface in your ServiceProvider.
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
    ],

];
