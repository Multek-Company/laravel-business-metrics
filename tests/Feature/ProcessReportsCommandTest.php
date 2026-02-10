<?php

namespace Multek\BusinessMetrics\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Multek\BusinessMetrics\Jobs\ProcessReportJob;
use Multek\BusinessMetrics\Tests\Fixtures\DummyReport;
use Multek\BusinessMetrics\Tests\TestCase;

class ProcessReportsCommandTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('business-metrics.reports', [
            DummyReport::class,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['db']->connection('testing')->statement(
            'CREATE TABLE IF NOT EXISTS dummy_report (id INTEGER PRIMARY KEY, value TEXT, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)'
        );
    }

    // --sync mode tests

    public function test_sync_command_runs_all_reports(): void
    {
        $this->artisan('business-metrics:reports', ['--sync' => true])
            ->expectsOutputToContain('Processing all registered reports synchronously')
            ->expectsOutputToContain('All reports processed')
            ->assertSuccessful();

        $rows = $this->app['db']->connection('testing')->select('SELECT * FROM dummy_report');
        $this->assertCount(1, $rows);
    }

    public function test_sync_command_runs_specific_report(): void
    {
        $this->artisan('business-metrics:reports', ['--report' => 'DummyReport', '--sync' => true])
            ->expectsOutputToContain('Processing report synchronously: DummyReport')
            ->expectsOutputToContain('Done')
            ->assertSuccessful();

        $rows = $this->app['db']->connection('testing')->select('SELECT * FROM dummy_report');
        $this->assertCount(1, $rows);
    }

    // Default (dispatch) mode tests

    public function test_command_dispatches_all_reports_to_queue(): void
    {
        Queue::fake();

        $this->artisan('business-metrics:reports')
            ->expectsOutputToContain('Dispatched 1 report(s) to queue')
            ->assertSuccessful();

        Queue::assertPushed(ProcessReportJob::class, function ($job) {
            return $job->reportClass === DummyReport::class;
        });
    }

    public function test_command_dispatches_specific_report_to_queue(): void
    {
        Queue::fake();

        $this->artisan('business-metrics:reports', ['--report' => 'DummyReport'])
            ->expectsOutputToContain('Dispatched report to queue: DummyReport')
            ->assertSuccessful();

        Queue::assertPushed(ProcessReportJob::class, function ($job) {
            return $job->reportClass === DummyReport::class;
        });
    }

    public function test_command_fails_for_unknown_report_in_dispatch_mode(): void
    {
        Queue::fake();

        $this->artisan('business-metrics:reports', ['--report' => 'NonExistentReport'])
            ->expectsOutputToContain("Report 'NonExistentReport' not found")
            ->assertFailed();

        Queue::assertNothingPushed();
    }
}
