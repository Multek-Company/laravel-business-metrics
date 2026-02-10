<?php

namespace Multek\BusinessMetrics\Tests\Feature;

use Illuminate\Support\Facades\File;
use Multek\BusinessMetrics\Tests\TestCase;

class MakeReportCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        // Clean up generated files (in testbench's app path)
        $dir = app_path('Reports');
        if (File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }

        parent::tearDown();
    }

    public function test_make_report_creates_file(): void
    {
        $this->artisan('make:report', ['name' => 'WeeklyRevenue'])
            ->assertSuccessful();

        $path = app_path('Reports/WeeklyRevenueReport.php');
        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertStringContainsString('class WeeklyRevenueReport extends BusinessReport', $content);
        $this->assertStringContainsString('analytics.weekly_revenue', $content);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS', $content);
    }

    public function test_make_report_appends_report_suffix(): void
    {
        $this->artisan('make:report', ['name' => 'Activation'])
            ->assertSuccessful();

        $path = app_path('Reports/ActivationReport.php');
        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertStringContainsString('class ActivationReport extends BusinessReport', $content);
    }

    public function test_make_report_does_not_double_suffix(): void
    {
        $this->artisan('make:report', ['name' => 'ActivationReport'])
            ->assertSuccessful();

        $path = app_path('Reports/ActivationReport.php');
        $this->assertFileExists($path);

        // Should NOT create ActivationReportReport
        $this->assertFileDoesNotExist(app_path('Reports/ActivationReportReport.php'));
    }

    public function test_make_report_fails_if_file_exists(): void
    {
        $this->artisan('make:report', ['name' => 'Duplicate'])
            ->assertSuccessful();

        $this->artisan('make:report', ['name' => 'Duplicate'])
            ->assertFailed();
    }
}
