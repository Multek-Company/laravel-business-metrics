<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create analytics schema
        $schema = config('business-metrics.analytics_schema', 'analytics');
        DB::statement("CREATE SCHEMA IF NOT EXISTS {$schema}");

        // Create business_events table (append-only event log)
        DB::statement("
            CREATE TABLE IF NOT EXISTS public.business_events (
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
            ON public.business_events (occurred_at DESC)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_business_events_name_occurred
            ON public.business_events (event_name, occurred_at DESC)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_business_events_company
            ON public.business_events (company_id, occurred_at DESC)
            WHERE company_id IS NOT NULL
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_business_events_entity
            ON public.business_events (entity_type, entity_id)
            WHERE entity_type IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('public.business_events');
    }
};
