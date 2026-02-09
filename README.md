# Laravel Business Metrics

Server-side business event tracking with automatic rollups for Laravel + PostgreSQL. Get real-time dashboards in Grafana and exploratory BI in Metabase — without streaming infrastructure.

## Architecture

```
App (Laravel) → public.business_events (append-only, transactional)
     ↓
Scheduler (1min/5min) → reads events → writes analytics.* rollups
     ↓
Grafana/Metabase → reads analytics.* (read-only user)
```

**Design principles:**
- `public` schema = transactional writes (app)
- `analytics` schema = read-only aggregations (dashboards)
- Events are append-only (auditable, no data loss)
- Rollups use `ON CONFLICT ... DO UPDATE` (idempotent, safe to re-run)

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- PostgreSQL 14+

## Installation

```bash
composer require multek/laravel-business-metrics
```

Publish config and enum stub:

```bash
php artisan vendor:publish --tag=business-metrics-config
php artisan vendor:publish --tag=business-metrics-enum
```

Run migrations and create analytics schema + rollup tables:

```bash
php artisan migrate
php artisan business-metrics:create-schema
```

## Configuration

Edit `config/business-metrics.php`:

### 1. Register your events

**Option A — Enum (recommended):**

Edit `app/Enums/BusinessEventType.php` and uncomment/add your events:

```php
enum BusinessEventType: string
{
    case UserSignedUp = 'user_signed_up';
    case RfqCreated = 'rfq_created';
    case OrderPaid = 'order_paid';
    // ...
}
```

Then point config to it:

```php
'events' => \App\Enums\BusinessEventType::class,
```

**Option B — Array:**

```php
'events' => [
    'user_signed_up',
    'rfq_created',
    'order_paid',
],
```

### 2. Define your funnel

```php
'funnel_stages' => [
    'user_signed_up',
    'company_created',
    'rfq_created',
    'quote_received',
    'order_created',
    'order_paid',
],
```

### 3. Configure rollups

The default rollups work well for most cases. Adjust schedules and retention as needed.

## Usage

### Logging events

**Via Facade:**

```php
use Multek\BusinessMetrics\Facades\BusinessEvent;

// Simple event
BusinessEvent::log('user_signed_up', actorUserId: $user->id);

// Event with context
BusinessEvent::log(
    eventName: 'order_paid',
    properties: ['value' => 15000.00, 'currency' => 'BRL', 'payment_method' => 'pix'],
    actorUserId: auth()->id(),
    companyId: $order->company_id,
    entityType: 'order',
    entityId: $order->id,
);

// Within a DB transaction (guarantees consistency)
DB::transaction(function () use ($order) {
    $order->update(['status' => 'paid']);

    BusinessEvent::logInTransaction(
        eventName: 'order_paid',
        properties: ['value' => $order->total],
        companyId: $order->company_id,
        entityType: 'order',
        entityId: $order->id,
    );
});
```

### Via Trait on Models

```php
use Multek\BusinessMetrics\Traits\HasBusinessEvents;

class Order extends Model
{
    use HasBusinessEvents;

    protected string $businessEntityType = 'order';

    protected function businessEventProperties(): array
    {
        return [
            'value' => $this->total,
            'currency' => 'BRL',
        ];
    }
}

// Then anywhere in your code:
$order->emitBusinessEvent('order_created');
$order->emitBusinessEvent('order_paid', ['payment_method' => 'pix']);

// Within a transaction:
$order->emitBusinessEventInTransaction('order_paid');
```

### Async events (queue)

Set in `.env`:

```
BUSINESS_METRICS_QUEUE=analytics
```

Events will be dispatched to the queue instead of writing synchronously. Use sync (`null`) for critical events like payments.

## Artisan Commands

```bash
# Create analytics schema and rollup tables
php artisan business-metrics:create-schema

# Process rollups manually
php artisan business-metrics:rollups --all
php artisan business-metrics:rollups --rollup=events_minute

# List registered events
php artisan business-metrics:events

# Prune old events
php artisan business-metrics:prune
php artisan business-metrics:prune --days=90
```

## Rollup Tables

The package creates these tables in the `analytics` schema:

| Table | Granularity | Use Case |
|-------|-------------|----------|
| `analytics.events_minute` | 1 min | Realtime curves in Grafana |
| `analytics.events_hour` | 1 hour | Hourly trends, today vs last week |
| `analytics.events_daily` | 1 day | Daily KPIs, growth, MoM |
| `analytics.funnel_daily` | 1 day | Business funnel by company |

Each rollup table contains:
- `event_count` — total events
- `distinct_companies` — unique companies
- `distinct_users` — unique users
- `total_value` — sum of `properties->value`

Rollups are **idempotent** — safe to re-run anytime.

## Grafana Queries (Examples)

### Orders per minute (realtime)

```sql
SELECT bucket AS time, event_count AS orders
FROM analytics.events_minute
WHERE event_name = 'order_created'
  AND bucket >= NOW() - INTERVAL '1 hour'
ORDER BY bucket
```

### Today vs last week

```sql
SELECT
    bucket::time AS time_of_day,
    event_count AS today
FROM analytics.events_minute
WHERE event_name = 'order_created'
  AND bucket::date = CURRENT_DATE

UNION ALL

SELECT
    (bucket + INTERVAL '7 days')::time AS time_of_day,
    event_count AS last_week
FROM analytics.events_minute
WHERE event_name = 'order_created'
  AND bucket::date = CURRENT_DATE - INTERVAL '7 days'
ORDER BY time_of_day
```

### Daily GMV

```sql
SELECT bucket AS time, total_value AS gmv
FROM analytics.events_daily
WHERE event_name = 'order_paid'
ORDER BY bucket DESC
LIMIT 30
```

## Connecting Dashboards

### Grafana

1. Add PostgreSQL data source pointing to your DB (use read-only user)
2. Grant permissions:

```sql
CREATE USER grafana_ro WITH PASSWORD 'secure_password';
GRANT USAGE ON SCHEMA analytics TO grafana_ro;
GRANT SELECT ON ALL TABLES IN SCHEMA analytics TO grafana_ro;
ALTER DEFAULT PRIVILEGES IN SCHEMA analytics GRANT SELECT ON TABLES TO grafana_ro;
```

3. Create dashboards querying `analytics.*` tables

### Metabase

Same read-only user. Point Metabase to the same DB, it will discover both `public` and `analytics` schemas for ad-hoc exploration.

## Scaling Roadmap

| Phase | Trigger | Action |
|-------|---------|--------|
| **Now** | Starting out | Postgres + analytics schema + Grafana |
| **Phase 1** | Dashboard queries slow down primary | Add read replica, point dashboards there |
| **Phase 2** | Complex joins, multiple sources | Introduce BigQuery + dbt |
| **Phase 3** | Real-time automation needs | Add Pub/Sub or Kafka |

The package is designed so that when you move to a warehouse, you replicate the same `business_events` table and rollup structure — no redesign needed.

## License

MIT
