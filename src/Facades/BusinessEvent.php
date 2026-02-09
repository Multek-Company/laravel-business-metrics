<?php

namespace Multek\BusinessMetrics\Facades;

use Illuminate\Support\Facades\Facade;
use Multek\BusinessMetrics\Services\BusinessEventLogger;

/**
 * @method static \Multek\BusinessMetrics\Models\BusinessEvent|null log(string $eventName, array $properties = [], ?int $actorUserId = null, ?int $companyId = null, ?string $entityType = null, mixed $entityId = null, ?string $dedupeKey = null)
 * @method static \Multek\BusinessMetrics\Models\BusinessEvent logInTransaction(string $eventName, array $properties = [], ?int $actorUserId = null, ?int $companyId = null, ?string $entityType = null, mixed $entityId = null, ?string $dedupeKey = null)
 * @method static array registeredEvents()
 *
 * @see \Multek\BusinessMetrics\Services\BusinessEventLogger
 */
class BusinessEvent extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BusinessEventLogger::class;
    }
}
