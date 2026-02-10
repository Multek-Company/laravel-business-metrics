<?php

namespace Multek\BusinessMetrics\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateAnalyticsSchemaCommand extends Command
{
    protected $signature = 'business-metrics:create-schema';
    protected $description = 'Create the analytics PostgreSQL schema and report tables';

    public function handle(): int
    {
        $connection = config('business-metrics.connection');
        $schema = config('business-metrics.analytics_schema', 'analytics');
        $db = DB::connection($connection);

        $this->info("Creating schema '{$schema}'...");
        $db->statement("CREATE SCHEMA IF NOT EXISTS {$schema}");

        // Create tables for each registered report
        $reportClasses = config('business-metrics.reports', []);

        if (empty($reportClasses)) {
            $this->warn('No reports registered in config/business-metrics.php.');
            $this->line('  Add report classes to the "reports" array, then re-run this command.');
        }

        foreach ($reportClasses as $class) {
            $report = new $class();
            $db->statement($report->schema());
            $this->info("  Created {$report->table()}");
        }

        $this->info('Analytics schema created successfully!');
        $this->newLine();
        $this->info('Next steps:');
        $this->line('  1. Run migrations: php artisan migrate');
        $this->line('  2. Configure events in config/business-metrics.php');
        $this->line('  3. Start logging: BusinessEvent::log(\'user_signed_up\', [...])');

        return self::SUCCESS;
    }
}
