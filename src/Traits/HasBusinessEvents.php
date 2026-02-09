<?php

namespace Multek\BusinessMetrics\Traits;

use Multek\BusinessMetrics\Facades\BusinessEvent;

/**
 * Add to any Eloquent model that should emit business events.
 *
 * Usage:
 *   class Order extends Model
 *   {
 *       use HasBusinessEvents;
 *
 *       protected string $businessEntityType = 'order';
 *
 *       // Optional: override to customize context
 *       protected function businessEventProperties(): array
 *       {
 *           return [
 *               'value' => $this->total,
 *               'currency' => 'BRL',
 *           ];
 *       }
 *   }
 *
 * Then:
 *   $order->emitBusinessEvent('order_created');
 *   $order->emitBusinessEvent('order_paid', ['payment_method' => 'pix']);
 */
trait HasBusinessEvents
{
    /**
     * Emit a business event tied to this model.
     */
    public function emitBusinessEvent(
        string $eventName,
        array $extraProperties = [],
        ?int $actorUserId = null,
        ?string $dedupeKey = null,
    ): void {
        $properties = array_merge(
            $this->businessEventProperties(),
            $extraProperties,
        );

        BusinessEvent::log(
            eventName: $eventName,
            properties: $properties,
            actorUserId: $actorUserId ?? $this->resolveActorUserId(),
            companyId: $this->resolveCompanyId(),
            entityType: $this->resolveEntityType(),
            entityId: $this->getKey(),
            dedupeKey: $dedupeKey,
        );
    }

    /**
     * Emit a business event within the current DB transaction.
     */
    public function emitBusinessEventInTransaction(
        string $eventName,
        array $extraProperties = [],
        ?int $actorUserId = null,
        ?string $dedupeKey = null,
    ): void {
        $properties = array_merge(
            $this->businessEventProperties(),
            $extraProperties,
        );

        BusinessEvent::logInTransaction(
            eventName: $eventName,
            properties: $properties,
            actorUserId: $actorUserId ?? $this->resolveActorUserId(),
            companyId: $this->resolveCompanyId(),
            entityType: $this->resolveEntityType(),
            entityId: $this->getKey(),
            dedupeKey: $dedupeKey,
        );
    }

    /**
     * Default properties included with every event from this model.
     * Override in your model to add context (value, currency, etc.)
     */
    protected function businessEventProperties(): array
    {
        return [];
    }

    /**
     * Resolve the entity type string.
     */
    protected function resolveEntityType(): string
    {
        return $this->businessEntityType
            ?? strtolower(class_basename(static::class));
    }

    /**
     * Resolve the company/tenant ID.
     * Override if your model uses a different column or relation.
     */
    protected function resolveCompanyId(): ?int
    {
        return $this->company_id ?? $this->tenant_id ?? null;
    }

    /**
     * Resolve the acting user ID.
     * Override if your model stores the actor differently.
     */
    protected function resolveActorUserId(): ?int
    {
        return $this->user_id ?? auth()->id();
    }
}
