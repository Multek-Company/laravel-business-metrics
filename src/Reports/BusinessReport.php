<?php

namespace Multek\BusinessMetrics\Reports;

abstract class BusinessReport
{
    /**
     * The target table name (e.g. 'analytics.activation_rate').
     */
    abstract public function table(): string;

    /**
     * The CREATE TABLE IF NOT EXISTS SQL for the analytics table.
     */
    abstract public function schema(): string;

    /**
     * The INSERT ... ON CONFLICT DO UPDATE SQL that populates the table.
     * Should include a lookback window so only recent data is recomputed.
     */
    abstract public function query(): string;

    /**
     * Cron expression for how often this report runs.
     */
    abstract public function schedule(): string;

    /**
     * Number of days to retain rows. Return null to keep forever (default).
     */
    public function retentionDays(): ?int
    {
        return null;
    }

    /**
     * Column used for pruning when retentionDays() is set.
     */
    public function retentionColumn(): string
    {
        return 'updated_at';
    }

    /**
     * Database connection name. Defaults to the package connection.
     */
    public function connection(): ?string
    {
        return config('business-metrics.connection');
    }
}
