<?php

namespace Multek\BusinessMetrics\Tests\Unit;

use Illuminate\Support\Facades\Queue;
use Multek\BusinessMetrics\Jobs\ProcessReportJob;
use Multek\BusinessMetrics\Services\ReportProcessor;
use Multek\BusinessMetrics\Tests\Fixtures\DummyReport;
use Multek\BusinessMetrics\Tests\Fixtures\RetentionReport;
use Multek\BusinessMetrics\Tests\TestCase;

class ProcessReportJobTest extends TestCase
{
    public function test_unique_id_returns_report_class(): void
    {
        $job = new ProcessReportJob(DummyReport::class);

        $this->assertSame(DummyReport::class, $job->uniqueId());
    }

    public function test_different_reports_have_different_unique_ids(): void
    {
        $job1 = new ProcessReportJob(DummyReport::class);
        $job2 = new ProcessReportJob(RetentionReport::class);

        $this->assertNotSame($job1->uniqueId(), $job2->uniqueId());
    }

    public function test_job_has_correct_retry_config(): void
    {
        $job = new ProcessReportJob(DummyReport::class);

        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->backoff);
    }

    public function test_job_uses_configured_queue(): void
    {
        config(['business-metrics.reports_queue' => 'analytics']);

        $job = new ProcessReportJob(DummyReport::class);

        $this->assertSame('analytics', $job->queue);
    }

    public function test_job_uses_default_queue_when_not_configured(): void
    {
        config(['business-metrics.reports_queue' => 'default']);

        $job = new ProcessReportJob(DummyReport::class);

        $this->assertSame('default', $job->queue);
    }

    public function test_job_can_be_dispatched(): void
    {
        Queue::fake();

        ProcessReportJob::dispatch(DummyReport::class);

        Queue::assertPushed(ProcessReportJob::class, function ($job) {
            return $job->reportClass === DummyReport::class;
        });
    }

    public function test_handle_runs_report_via_processor(): void
    {
        $db = $this->app['db']->connection('testing');
        $db->statement('CREATE TABLE IF NOT EXISTS dummy_report (id INTEGER PRIMARY KEY, value TEXT, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');

        $processor = new ReportProcessor([DummyReport::class]);
        $job = new ProcessReportJob(DummyReport::class);

        $job->handle($processor);

        $rows = $db->select('SELECT * FROM dummy_report');
        $this->assertCount(1, $rows);
        $this->assertEquals('test', $rows[0]->value);
    }
}
