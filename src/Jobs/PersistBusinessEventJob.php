<?php

namespace Multek\BusinessMetrics\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Multek\BusinessMetrics\Services\BusinessEventLogger;

class PersistBusinessEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $data,
    ) {}

    public function handle(BusinessEventLogger $logger): void
    {
        $logger->persist($this->data);
    }
}
