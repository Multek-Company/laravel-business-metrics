<?php

namespace Multek\BusinessMetrics\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Multek\BusinessMetrics\Reports\BusinessReport;

class ReportProcessor
{
    /** @var array<class-string<BusinessReport>> */
    protected array $reportClasses;

    public function __construct(array $reportClasses)
    {
        $this->reportClasses = $reportClasses;
    }

    /**
     * Run a single report by class name (short or fully-qualified).
     */
    public function process(string $reportName): void
    {
        $report = $this->resolve($reportName);

        if (! $report) {
            Log::warning("Report '{$reportName}' not found in registered reports.");
            return;
        }

        $this->run($report);
    }

    /**
     * Run all registered reports.
     */
    public function processAll(): void
    {
        foreach ($this->reportClasses as $class) {
            $this->run(new $class());
        }
    }

    /**
     * Execute a report's query and optionally prune old rows.
     */
    public function run(BusinessReport $report): void
    {
        $connection = $report->connection();

        DB::connection($connection)->statement($report->query());

        if ($report->retentionDays() !== null) {
            $table = $report->table();
            $column = $report->retentionColumn();
            $days = $report->retentionDays();

            DB::connection($connection)->statement(
                "DELETE FROM {$table} WHERE {$column} < NOW() - INTERVAL '{$days} days'"
            );
        }

        Log::debug("Report '" . class_basename($report) . "' processed successfully.");
    }

    /**
     * Resolve a report name to an instance.
     */
    protected function resolve(string $reportName): ?BusinessReport
    {
        foreach ($this->reportClasses as $class) {
            if ($class === $reportName || class_basename($class) === $reportName) {
                return new $class();
            }
        }

        return null;
    }

    /**
     * Get all registered report instances.
     *
     * @return array<BusinessReport>
     */
    public function reports(): array
    {
        return array_map(fn ($class) => new $class(), $this->reportClasses);
    }
}
