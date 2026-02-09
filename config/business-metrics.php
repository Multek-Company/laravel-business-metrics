<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The database connection used for business events and analytics tables.
    | By default it uses your app's default connection. When you add a read
    | replica, create a separate connection and point analytics reads there.
    |
    */
    'connection' => env('BUSINESS_METRICS_CONNECTION', config('database.default')),

    /*
    |--------------------------------------------------------------------------
    | Analytics Schema
    |--------------------------------------------------------------------------
    |
    | The PostgreSQL schema where rollup tables and materialized views live.
    | This keeps analytical data separate from your transactional tables.
    |
    */
    'analytics_schema' => env('BUSINESS_METRICS_SCHEMA', 'analytics'),

    /*
    |--------------------------------------------------------------------------
    | Events Table
    |--------------------------------------------------------------------------
    |
    | The table where business events are stored (append-only).
    | Recommended: keep in 'public' schema for transactional consistency.
    |
    */
    'events_table' => env('BUSINESS_METRICS_EVENTS_TABLE', 'public.business_events'),

    /*
    |--------------------------------------------------------------------------
    | Registered Events
    |--------------------------------------------------------------------------
    |
    | Define all valid business event names here. The logger will reject
    | any event not in this list, protecting you from typos and inconsistency.
    |
    | You can use a PHP enum by setting this to a class string:
    |   'events' => \App\Enums\BusinessEventType::class,
    |
    | Or define them inline as an array:
    |   'events' => ['user_signed_up', 'order_created', ...]
    |
    */
    'events' => [
        // Auth & Onboarding
        'user_signed_up',
        'user_verified_email',
        'company_created',
        'company_onboarding_completed',

        // Core Funnel (customize per business)
        // 'rfq_created',
        // 'supplier_invited',
        // 'supplier_responded',
        // 'quote_received',
        // 'quote_selected',
        // 'order_created',
        // 'order_paid',
        // 'order_delivered',

        // Monetization
        // 'subscription_started',
        // 'subscription_renewed',
        // 'subscription_cancelled',
        // 'payment_confirmed',
        // 'payment_failed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rollups Configuration
    |--------------------------------------------------------------------------
    |
    | Define which rollup tables to generate and their refresh intervals.
    | Each rollup aggregates business_events into fast-query tables.
    |
    */
    'rollups' => [

        'events_minute' => [
            'enabled' => true,
            'table' => 'analytics.events_minute',
            'interval' => 'minute',         // minute, hour, day
            'schedule' => '* * * * *',       // every minute
            'retention_days' => 7,           // auto-prune older than this
        ],

        'events_hour' => [
            'enabled' => true,
            'table' => 'analytics.events_hour',
            'interval' => 'hour',
            'schedule' => '*/5 * * * *',     // every 5 minutes
            'retention_days' => 90,
        ],

        'events_daily' => [
            'enabled' => true,
            'table' => 'analytics.events_daily',
            'interval' => 'day',
            'schedule' => '5 * * * *',       // every hour at :05
            'retention_days' => 730,         // 2 years
        ],

        'funnel_daily' => [
            'enabled' => true,
            'table' => 'analytics.funnel_daily',
            'interval' => 'day',
            'schedule' => '10 * * * *',      // every hour at :10
            'retention_days' => 730,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Funnel Stages
    |--------------------------------------------------------------------------
    |
    | Define the ordered stages of your business funnel.
    | Each stage maps to an event_name in business_events.
    | Used by the funnel_daily rollup.
    |
    */
    'funnel_stages' => [
        // 'user_signed_up',
        // 'company_created',
        // 'rfq_created',
        // 'quote_received',
        // 'order_created',
        // 'order_paid',
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Properties Validation
    |--------------------------------------------------------------------------
    |
    | When true, logs a warning if event properties don't match the expected
    | schema. Useful in development/staging to catch issues early.
    |
    */
    'validate_properties' => env('BUSINESS_METRICS_VALIDATE_PROPS', false),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Set to a queue name to dispatch event logging asynchronously.
    | Set to null/false for synchronous (same-transaction) logging.
    |
    | Recommendation: use null (sync) for critical events like payments,
    | and a queue for high-volume, non-critical events.
    |
    */
    'queue' => env('BUSINESS_METRICS_QUEUE', null),

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | Auto-prune old business_events rows. Set to 0 to keep forever.
    | Rollup tables have their own retention_days above.
    |
    */
    'events_retention_days' => env('BUSINESS_METRICS_RETENTION_DAYS', 365),

];
