<?php

namespace Multek\BusinessMetrics\Commands;

use Illuminate\Console\Command;
use Multek\BusinessMetrics\Jobs\ProcessReportJob;
use Multek\BusinessMetrics\Services\ReportProcessor;

class ProcessReportsCommand extends Command
{
    protected $signature = 'business-metrics:reports
                            {--report= : Process a specific report by class name}
                            {--sync : Run synchronously instead of dispatching to queue}';

    protected $description = 'Run registered business metric reports';

    public function handle(ReportProcessor $processor): int
    {
        $reportName = $this->option('report');
        $sync = $this->option('sync');

        if ($sync) {
            return $this->runSync($processor, $reportName);
        }

        return $this->dispatch($processor, $reportName);
    }

    protected function runSync(ReportProcessor $processor, ?string $reportName): int
    {
        if ($reportName) {
            $this->info("Processing report synchronously: {$reportName}");
            $processor->process($reportName);
            $this->info('Done.');
            return self::SUCCESS;
        }

        $this->info('Processing all registered reports synchronously...');
        $processor->processAll();
        $this->info('All reports processed.');

        return self::SUCCESS;
    }

    protected function dispatch(ReportProcessor $processor, ?string $reportName): int
    {
        $reports = $processor->reports();

        if ($reportName) {
            $matched = collect($reports)->first(
                fn ($r) => class_basename($r) === $reportName || get_class($r) === $reportName
            );

            if (! $matched) {
                $this->error("Report '{$reportName}' not found in registered reports.");
                return self::FAILURE;
            }

            ProcessReportJob::dispatch(get_class($matched));
            $this->info("Dispatched report to queue: {$reportName}");
            return self::SUCCESS;
        }

        foreach ($reports as $report) {
            ProcessReportJob::dispatch(get_class($report));
        }

        $this->info('Dispatched ' . count($reports) . ' report(s) to queue.');

        return self::SUCCESS;
    }
}
