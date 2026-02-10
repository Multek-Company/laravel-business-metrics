<?php

namespace Multek\BusinessMetrics\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Multek\BusinessMetrics\Services\ReportProcessor;

class ProcessReportJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly string $reportClass,
    ) {
        $this->onQueue(config('business-metrics.reports_queue', 'default'));
    }

    public function handle(ReportProcessor $processor): void
    {
        $report = new $this->reportClass();

        $processor->run($report);
    }

    public function uniqueId(): string
    {
        return $this->reportClass;
    }
}
