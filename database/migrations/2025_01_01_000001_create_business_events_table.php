<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create analytics schema
        $schema = config('business-metrics.analytics_schema', 'analytics');
        DB::statement("CREATE SCHEMA IF NOT EXISTS {$schema}");

        // Create business_events table (append-only event log)
        $eventsTable = config('business-metrics.events_table', 'public.business_events');
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$eventsTable} (
                id UUID PRIMARY KEY,
                event_name VARCHAR(100) NOT NULL,
                occurred_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                actor_user_id BIGINT,
                company_id BIGINT,
                entity_type VARCHAR(50),
                entity_id VARCHAR(50),
                properties JSONB DEFAULT '{}',
                request_id VARCHAR(100),
                dedupe_key VARCHAR(200),
                CONSTRAINT uq_business_events_dedupe UNIQUE (dedupe_key)
            )
        ");

        // Indices for common query patterns
        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_business_events_occurred
            ON {$eventsTable} (occurred_at DESC)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_business_events_name_occurred
            ON {$eventsTable} (event_name, occurred_at DESC)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_business_events_company
            ON {$eventsTable} (company_id, occurred_at DESC)
            WHERE company_id IS NOT NULL
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_business_events_entity
            ON {$eventsTable} (entity_type, entity_id)
            WHERE entity_type IS NOT NULL
        ");
    }

    public function down(): void
    {
        $eventsTable = config('business-metrics.events_table', 'public.business_events');
        DB::statement("DROP TABLE IF EXISTS {$eventsTable}");

        $schema = config('business-metrics.analytics_schema', 'analytics');
        DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
    }
};
