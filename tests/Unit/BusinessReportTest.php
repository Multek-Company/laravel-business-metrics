<?php

namespace Multek\BusinessMetrics\Tests\Unit;

use Multek\BusinessMetrics\Tests\Fixtures\DummyReport;
use Multek\BusinessMetrics\Tests\Fixtures\RetentionReport;
use PHPUnit\Framework\TestCase;

class BusinessReportTest extends TestCase
{
    public function test_report_returns_table_name(): void
    {
        $report = new DummyReport();

        $this->assertEquals('analytics.dummy_report', $report->table());
    }

    public function test_report_returns_schema_sql(): void
    {
        $report = new DummyReport();

        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS', $report->schema());
        $this->assertStringContainsString('dummy_report', $report->schema());
    }

    public function test_report_returns_query_sql(): void
    {
        $report = new DummyReport();

        $this->assertStringContainsString('INSERT INTO dummy_report', $report->query());
        $this->assertStringContainsString('ON CONFLICT', $report->query());
    }

    public function test_report_returns_schedule(): void
    {
        $report = new DummyReport();

        $this->assertEquals('0 */6 * * *', $report->schedule());
    }

    public function test_retention_days_defaults_to_null(): void
    {
        $report = new DummyReport();

        $this->assertNull($report->retentionDays());
    }

    public function test_retention_column_defaults_to_updated_at(): void
    {
        $report = new DummyReport();

        $this->assertEquals('updated_at', $report->retentionColumn());
    }

    public function test_retention_report_has_custom_retention(): void
    {
        $report = new RetentionReport();

        $this->assertEquals(90, $report->retentionDays());
        $this->assertEquals('created_at', $report->retentionColumn());
    }

    public function test_retention_report_schedule(): void
    {
        $report = new RetentionReport();

        $this->assertEquals('*/30 * * * *', $report->schedule());
    }
}
