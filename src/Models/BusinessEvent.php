<?php

namespace Multek\BusinessMetrics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BusinessEvent extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'event_name',
        'occurred_at',
        'actor_user_id',
        'company_id',
        'entity_type',
        'entity_id',
        'properties',
        'request_id',
        'dedupe_key',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'properties' => 'array',
    ];

    public function getTable(): string
    {
        return config('business-metrics.events_table', 'public.business_events');
    }

    public function getConnectionName(): ?string
    {
        return config('business-metrics.connection') ?: parent::getConnectionName();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForEvent($query, string $eventName)
    {
        return $query->where('event_name', $eventName);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('actor_user_id', $userId);
    }

    public function scopeForEntity($query, string $entityType, mixed $entityId = null)
    {
        $query->where('entity_type', $entityType);

        if ($entityId !== null) {
            $query->where('entity_id', (string) $entityId);
        }

        return $query;
    }

    public function scopeSince($query, $datetime)
    {
        return $query->where('occurred_at', '>=', $datetime);
    }

    public function scopeBetween($query, $start, $end)
    {
        return $query->whereBetween('occurred_at', [$start, $end]);
    }
}
