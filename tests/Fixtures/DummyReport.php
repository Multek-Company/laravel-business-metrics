<?php

namespace Multek\BusinessMetrics\Tests\Fixtures;

use Multek\BusinessMetrics\Reports\BusinessReport;

class DummyReport extends BusinessReport
{
    public bool $queryExecuted = false;

    public function table(): string
    {
        return 'analytics.dummy_report';
    }

    public function schema(): string
    {
        return <<<'SQL'
            CREATE TABLE IF NOT EXISTS dummy_report (
                id INTEGER PRIMARY KEY,
                value TEXT,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        SQL;
    }

    public function query(): string
    {
        return "INSERT INTO dummy_report (id, value) VALUES (1, 'test') ON CONFLICT (id) DO UPDATE SET value = 'test', updated_at = CURRENT_TIMESTAMP";
    }

    public function schedule(): string
    {
        return '0 */6 * * *';
    }
}
