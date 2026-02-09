<?php

namespace Multek\BusinessMetrics\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Multek\BusinessMetrics\Jobs\PersistBusinessEventJob;
use Multek\BusinessMetrics\Models\BusinessEvent;

class BusinessEventLogger
{
    public function __construct(
        protected ?string $connection,
        protected string $eventsTable,
        protected array $validEvents,
        protected ?string $queue,
    ) {}

    /**
     * Log a business event.
     *
     * @param string      $eventName   Must be a registered event name.
     * @param array       $properties  JSONB context (value, currency, source, etc.)
     * @param int|null    $actorUserId The user who caused this event.
     * @param int|null    $companyId   The company/tenant context.
     * @param string|null $entityType  Related entity (e.g., 'order', 'rfq').
     * @param mixed       $entityId    Related entity ID.
     * @param string|null $dedupeKey   Optional idempotency key.
     */
    public function log(
        string $eventName,
        array $properties = [],
        ?int $actorUserId = null,
        ?int $companyId = null,
        ?string $entityType = null,
        mixed $entityId = null,
        ?string $dedupeKey = null,
    ): ?BusinessEvent {
        $this->validateEventName($eventName);

        $data = [
            'id' => Str::uuid()->toString(),
            'event_name' => $eventName,
            'occurred_at' => now(),
            'actor_user_id' => $actorUserId,
            'company_id' => $companyId,
            'entity_type' => $entityType,
            'entity_id' => $entityId ? (string) $entityId : null,
            'properties' => json_encode($properties),
            'request_id' => request()?->header('X-Request-ID'),
            'dedupe_key' => $dedupeKey,
        ];

        // Async: dispatch to queue
        if ($this->queue) {
            PersistBusinessEventJob::dispatch($data)
                ->onQueue($this->queue);
            return null;
        }

        // Sync: insert in current transaction
        return $this->persist($data);
    }

    /**
     * Log within an existing DB transaction (guarantees consistency).
     */
    public function logInTransaction(
        string $eventName,
        array $properties = [],
        ?int $actorUserId = null,
        ?int $companyId = null,
        ?string $entityType = null,
        mixed $entityId = null,
        ?string $dedupeKey = null,
    ): BusinessEvent {
        $this->validateEventName($eventName);

        return $this->persist([
            'id' => Str::uuid()->toString(),
            'event_name' => $eventName,
            'occurred_at' => now(),
            'actor_user_id' => $actorUserId,
            'company_id' => $companyId,
            'entity_type' => $entityType,
            'entity_id' => $entityId ? (string) $entityId : null,
            'properties' => json_encode($properties),
            'request_id' => request()?->header('X-Request-ID'),
            'dedupe_key' => $dedupeKey,
        ]);
    }

    /**
     * Persist event data to the database.
     */
    public function persist(array $data): BusinessEvent
    {
        // Handle deduplication
        if ($dedupeKey = $data['dedupe_key'] ?? null) {
            $existing = BusinessEvent::on($this->connection)
                ->where('dedupe_key', $dedupeKey)
                ->first();

            if ($existing) {
                Log::debug("BusinessEvent deduplicated: {$dedupeKey}");
                return $existing;
            }
        }

        $event = new BusinessEvent();
        $event->setConnection($this->connection);
        $event->fill($data);
        $event->save();

        return $event;
    }

    /**
     * Validate event name against registered events.
     */
    protected function validateEventName(string $eventName): void
    {
        if (empty($this->validEvents)) {
            return; // No validation if no events registered
        }

        if (! in_array($eventName, $this->validEvents, true)) {
            throw new InvalidArgumentException(
                "Unknown business event: '{$eventName}'. Register it in config('business-metrics.events') or your BusinessEventType enum."
            );
        }
    }

    /**
     * Get all registered event names.
     */
    public function registeredEvents(): array
    {
        return $this->validEvents;
    }
}
