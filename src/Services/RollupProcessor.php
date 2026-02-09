<?php

namespace Multek\BusinessMetrics\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RollupProcessor
{
    public function __construct(
        protected ?string $connection,
        protected string $eventsTable,
        protected array $rollups,
        protected array $funnelStages,
    ) {}

    /**
     * Process a specific rollup by name.
     */
    public function process(string $rollupName): void
    {
        $config = $this->rollups[$rollupName] ?? null;

        if (! $config || ! ($config['enabled'] ?? false)) {
            Log::warning("Rollup '{$rollupName}' not found or disabled.");
            return;
        }

        match ($rollupName) {
            'funnel_daily' => $this->processFunnelDaily($config),
            default => $this->processEventRollup($rollupName, $config),
        };
    }

    /**
     * Process all enabled rollups.
     */
    public function processAll(): void
    {
        foreach ($this->rollups as $name => $config) {
            if ($config['enabled'] ?? false) {
                $this->process($name);
            }
        }
    }

    /**
     * Generic event count rollup (events_minute, events_hour, events_daily).
     */
    protected function processEventRollup(string $name, array $config): void
    {
        $table = $config['table'];
        $interval = $config['interval'];
        $truncInterval = $this->pgTruncInterval($interval);
        $lookback = $this->lookbackInterval($interval);

        $sql = <<<SQL
            INSERT INTO {$table} (bucket, event_name, event_count, distinct_companies, distinct_users, total_value)
            SELECT
                date_trunc('{$truncInterval}', occurred_at) AS bucket,
                event_name,
                COUNT(*) AS event_count,
                COUNT(DISTINCT company_id) AS distinct_companies,
                COUNT(DISTINCT actor_user_id) AS distinct_users,
                COALESCE(SUM((properties->>'value')::numeric), 0) AS total_value
            FROM {$this->eventsTable}
            WHERE occurred_at >= date_trunc('{$truncInterval}', NOW() - INTERVAL '{$lookback}')
            GROUP BY 1, 2
            ON CONFLICT (bucket, event_name)
            DO UPDATE SET
                event_count = EXCLUDED.event_count,
                distinct_companies = EXCLUDED.distinct_companies,
                distinct_users = EXCLUDED.distinct_users,
                total_value = EXCLUDED.total_value,
                updated_at = NOW()
        SQL;

        DB::connection($this->connection)->statement($sql);

        // Prune old data
        $this->pruneRollup($table, $config['retention_days'] ?? 365);

        Log::debug("Rollup '{$name}' processed successfully.");
    }

    /**
     * Funnel daily rollup — pivots events into stage columns.
     */
    protected function processFunnelDaily($config): void
    {
        if (empty($this->funnelStages)) {
            Log::warning('No funnel stages configured. Skipping funnel_daily rollup.');
            return;
        }

        $table = $config['table'];

        // Build dynamic pivot columns
        $stageColumns = [];
        $stageInsertColumns = [];
        $stageConflictUpdates = [];

        foreach ($this->funnelStages as $i => $stage) {
            $col = "stage_{$i}_{$stage}";
            $stageColumns[] = "COUNT(*) FILTER (WHERE event_name = '{$stage}') AS {$col}";
            $stageInsertColumns[] = $col;
            $stageConflictUpdates[] = "{$col} = EXCLUDED.{$col}";
        }

        $stageSelectSql = implode(",\n                ", $stageColumns);
        $stageInsertSql = implode(', ', $stageInsertColumns);
        $stageConflictSql = implode(",\n                ", $stageConflictUpdates);

        $stageNames = array_map(fn ($s) => "'{$s}'", $this->funnelStages);
        $stageFilter = implode(', ', $stageNames);

        $sql = <<<SQL
            INSERT INTO {$table} (day, company_id, {$stageInsertSql})
            SELECT
                date_trunc('day', occurred_at)::date AS day,
                company_id,
                {$stageSelectSql}
            FROM {$this->eventsTable}
            WHERE occurred_at >= date_trunc('day', NOW() - INTERVAL '2 days')
              AND event_name IN ({$stageFilter})
              AND company_id IS NOT NULL
            GROUP BY 1, 2
            ON CONFLICT (day, company_id)
            DO UPDATE SET
                {$stageConflictSql},
                updated_at = NOW()
        SQL;

        DB::connection($this->connection)->statement($sql);

        $this->pruneRollup($table, $config['retention_days'] ?? 730);

        Log::debug('Rollup funnel_daily processed successfully.');
    }

    /**
     * Remove rows older than retention period.
     */
    protected function pruneRollup(string $table, int $retentionDays): void
    {
        $column = str_contains($table, 'funnel') ? 'day' : 'bucket';

        DB::connection($this->connection)->statement(
            "DELETE FROM {$table} WHERE {$column} < NOW() - INTERVAL '{$retentionDays} days'"
        );
    }

    protected function pgTruncInterval(string $interval): string
    {
        return match ($interval) {
            'minute' => 'minute',
            'hour' => 'hour',
            'day' => 'day',
            default => 'hour',
        };
    }

    protected function lookbackInterval(string $interval): string
    {
        return match ($interval) {
            'minute' => '15 minutes',
            'hour' => '3 hours',
            'day' => '2 days',
            default => '3 hours',
        };
    }
}
