<?php

namespace Multek\BusinessMetrics\Commands;

use Illuminate\Console\Command;
use Multek\BusinessMetrics\Services\RollupProcessor;

class ProcessRollupsCommand extends Command
{
    protected $signature = 'business-metrics:rollups
                            {--rollup= : Process a specific rollup by name}
                            {--all : Process all enabled rollups}';

    protected $description = 'Process analytics rollup tables from business events';

    public function handle(RollupProcessor $processor): int
    {
        $rollupName = $this->option('rollup');

        if ($rollupName) {
            $this->info("Processing rollup: {$rollupName}");
            $processor->process($rollupName);
            $this->info("✓ Done.");
            return self::SUCCESS;
        }

        $this->info('Processing all enabled rollups...');
        $processor->processAll();
        $this->info('✓ All rollups processed.');

        return self::SUCCESS;
    }
}
