<?php

namespace Multek\BusinessMetrics\Tests\Fixtures;

use Multek\BusinessMetrics\Reports\BusinessReport;

class RetentionReport extends BusinessReport
{
    public function table(): string
    {
        return 'analytics.retention_report';
    }

    public function schema(): string
    {
        return <<<'SQL'
            CREATE TABLE IF NOT EXISTS retention_report (
                id INTEGER PRIMARY KEY,
                value TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        SQL;
    }

    public function query(): string
    {
        return "INSERT INTO retention_report (id, value) VALUES (1, 'data') ON CONFLICT (id) DO UPDATE SET value = 'data', created_at = CURRENT_TIMESTAMP";
    }

    public function schedule(): string
    {
        return '*/30 * * * *';
    }

    public function retentionDays(): ?int
    {
        return 90;
    }

    public function retentionColumn(): string
    {
        return 'created_at';
    }
}
