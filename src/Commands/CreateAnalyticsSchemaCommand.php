<?php

namespace Multek\BusinessMetrics\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateAnalyticsSchemaCommand extends Command
{
    protected $signature = 'business-metrics:create-schema';
    protected $description = 'Create the analytics PostgreSQL schema and rollup tables';

    public function handle(): int
    {
        $connection = config('business-metrics.connection');
        $schema = config('business-metrics.analytics_schema', 'analytics');
        $db = DB::connection($connection);

        $this->info("Creating schema '{$schema}'...");
        $db->statement("CREATE SCHEMA IF NOT EXISTS {$schema}");

        // Create rollup tables
        $rollups = config('business-metrics.rollups', []);

        foreach ($rollups as $name => $config) {
            if (! ($config['enabled'] ?? false)) {
                continue;
            }

            if ($name === 'funnel_daily') {
                $this->createFunnelDailyTable($db, $config['table']);
            } else {
                $this->createEventRollupTable($db, $config['table']);
            }

            $this->info("  ✓ {$config['table']}");
        }

        $this->info('Analytics schema created successfully!');
        $this->newLine();
        $this->info('Next steps:');
        $this->line('  1. Run migrations: php artisan migrate');
        $this->line('  2. Configure events in config/business-metrics.php');
        $this->line('  3. Start logging: BusinessEvent::log(\'user_signed_up\', [...])');

        return self::SUCCESS;
    }

    protected function createEventRollupTable($db, string $table): void
    {
        $db->statement("
            CREATE TABLE IF NOT EXISTS {$table} (
                bucket TIMESTAMPTZ NOT NULL,
                event_name VARCHAR(100) NOT NULL,
                event_count BIGINT NOT NULL DEFAULT 0,
                distinct_companies BIGINT NOT NULL DEFAULT 0,
                distinct_users BIGINT NOT NULL DEFAULT 0,
                total_value NUMERIC(15,2) NOT NULL DEFAULT 0,
                updated_at TIMESTAMPTZ DEFAULT NOW(),
                PRIMARY KEY (bucket, event_name)
            )
        ");

        // Index for time-range queries (Grafana)
        $safeName = str_replace('.', '_', $table);
        $db->statement("
            CREATE INDEX IF NOT EXISTS idx_{$safeName}_bucket
            ON {$table} (bucket DESC)
        ");
    }

    protected function createFunnelDailyTable($db, string $table): void
    {
        $stages = config('business-metrics.funnel_stages', []);

        $stageColumns = '';
        foreach ($stages as $i => $stage) {
            $col = "stage_{$i}_{$stage}";
            $stageColumns .= "                {$col} BIGINT NOT NULL DEFAULT 0,\n";
        }

        $db->statement("
            CREATE TABLE IF NOT EXISTS {$table} (
                day DATE NOT NULL,
                company_id BIGINT,
{$stageColumns}                updated_at TIMESTAMPTZ DEFAULT NOW(),
                PRIMARY KEY (day, company_id)
            )
        ");

        $safeName = str_replace('.', '_', $table);
        $db->statement("
            CREATE INDEX IF NOT EXISTS idx_{$safeName}_day
            ON {$table} (day DESC)
        ");
    }
}
