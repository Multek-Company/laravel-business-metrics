<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The database connection used for business events and analytics tables.
    | When null, uses your app's default connection. When you add a read
    | replica, create a separate connection and point analytics reads there.
    |
    */
    'connection' => env('BUSINESS_METRICS_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Analytics Schema
    |--------------------------------------------------------------------------
    |
    | The PostgreSQL schema where report tables live.
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
    | Reports
    |--------------------------------------------------------------------------
    |
    | Register your BusinessReport classes here. Each report defines its own
    | table schema, SQL query, and schedule. The package handles table
    | creation, scheduling, and optional pruning.
    |
    | Example:
    |   \App\Reports\ActivationRateReport::class,
    |   \App\Reports\WeeklyRevenueReport::class,
    |
    */
    'reports' => [

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
    | Reports Queue
    |--------------------------------------------------------------------------
    |
    | The queue name used to dispatch report processing jobs.
    | Defaults to 'default'. Set this to a dedicated queue if you want
    | to isolate report processing from other jobs.
    |
    */
    'reports_queue' => env('BUSINESS_METRICS_REPORTS_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | Auto-prune old business_events rows. Set to 0 to keep forever.
    | Report tables manage their own retention via retentionDays().
    |
    */
    'events_retention_days' => env('BUSINESS_METRICS_RETENTION_DAYS', 365 * 2),

];
