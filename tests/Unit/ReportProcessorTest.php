<?php

namespace Multek\BusinessMetrics\Tests\Unit;

use Multek\BusinessMetrics\Services\ReportProcessor;
use Multek\BusinessMetrics\Tests\Fixtures\DummyReport;
use Multek\BusinessMetrics\Tests\Fixtures\RetentionReport;
use Multek\BusinessMetrics\Tests\TestCase;

class ReportProcessorTest extends TestCase
{
    public function test_reports_returns_all_instances(): void
    {
        $processor = new ReportProcessor([
            DummyReport::class,
            RetentionReport::class,
        ]);

        $reports = $processor->reports();

        $this->assertCount(2, $reports);
        $this->assertInstanceOf(DummyReport::class, $reports[0]);
        $this->assertInstanceOf(RetentionReport::class, $reports[1]);
    }

    public function test_reports_returns_empty_when_none_registered(): void
    {
        $processor = new ReportProcessor([]);

        $this->assertCount(0, $processor->reports());
    }

    public function test_process_runs_query_on_database(): void
    {
        $db = $this->app['db']->connection('testing');
        $db->statement('CREATE TABLE IF NOT EXISTS dummy_report (id INTEGER PRIMARY KEY, value TEXT, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');

        $processor = new ReportProcessor([DummyReport::class]);
        $processor->processAll();

        $rows = $db->select('SELECT * FROM dummy_report');
        $this->assertCount(1, $rows);
        $this->assertEquals('test', $rows[0]->value);
    }

    public function test_process_specific_report_by_class_name(): void
    {
        $db = $this->app['db']->connection('testing');
        $db->statement('CREATE TABLE IF NOT EXISTS dummy_report (id INTEGER PRIMARY KEY, value TEXT, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');

        $processor = new ReportProcessor([DummyReport::class]);
        $processor->process(DummyReport::class);

        $rows = $db->select('SELECT * FROM dummy_report');
        $this->assertCount(1, $rows);
    }

    public function test_process_specific_report_by_short_name(): void
    {
        $db = $this->app['db']->connection('testing');
        $db->statement('CREATE TABLE IF NOT EXISTS dummy_report (id INTEGER PRIMARY KEY, value TEXT, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');

        $processor = new ReportProcessor([DummyReport::class]);
        $processor->process('DummyReport');

        $rows = $db->select('SELECT * FROM dummy_report');
        $this->assertCount(1, $rows);
    }

    public function test_process_unknown_report_does_not_throw(): void
    {
        $processor = new ReportProcessor([DummyReport::class]);

        // Should log a warning but not throw
        $processor->process('NonExistentReport');

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function test_upsert_is_idempotent(): void
    {
        $db = $this->app['db']->connection('testing');
        $db->statement('CREATE TABLE IF NOT EXISTS dummy_report (id INTEGER PRIMARY KEY, value TEXT, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');

        $processor = new ReportProcessor([DummyReport::class]);

        // Run twice — should not fail or duplicate
        $processor->processAll();
        $processor->processAll();

        $rows = $db->select('SELECT * FROM dummy_report');
        $this->assertCount(1, $rows);
    }
}
