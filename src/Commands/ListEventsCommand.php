<?php

namespace Multek\BusinessMetrics\Commands;

use Illuminate\Console\Command;
use Multek\BusinessMetrics\Services\BusinessEventLogger;

class ListEventsCommand extends Command
{
    protected $signature = 'business-metrics:events';

    protected $description = 'List all registered business event types';

    public function handle(BusinessEventLogger $logger): int
    {
        $events = $logger->registeredEvents();

        if (empty($events)) {
            $this->warn('No events registered. Add events to config/business-metrics.php or use a BusinessEventType enum.');
            return self::SUCCESS;
        }

        $this->info('Registered business events:');
        $this->newLine();

        $rows = array_map(fn (string $event, int $index) => [$index + 1, $event], $events, array_keys($events));

        $this->table(['#', 'Event Name'], $rows);

        return self::SUCCESS;
    }
}
