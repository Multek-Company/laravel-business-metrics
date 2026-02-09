<?php

namespace Multek\BusinessMetrics\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneEventsCommand extends Command
{
    protected $signature = 'business-metrics:prune
                            {--days= : Override retention days}';

    protected $description = 'Prune old business events beyond the retention period';

    public function handle(): int
    {
        $days = $this->option('days') ?? config('business-metrics.events_retention_days', 365);
        $days = (int) $days;

        if ($days <= 0) {
            $this->info('Pruning is disabled (retention days is 0).');
            return self::SUCCESS;
        }

        $connection = config('business-metrics.connection');
        $table = config('business-metrics.events_table', 'public.business_events');

        $deleted = DB::connection($connection)
            ->table(DB::raw($table))
            ->where('occurred_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$deleted} events older than {$days} days.");

        return self::SUCCESS;
    }
}
