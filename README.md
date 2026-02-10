# Laravel Business Metrics

Server-side business event tracking with flexible report generation for Laravel + PostgreSQL. Get real-time dashboards in Grafana and exploratory BI in Metabase — without streaming infrastructure.

## Architecture

```
App (Laravel) → public.business_events (append-only, transactional)
     ↓
Scheduler (every minute)
     └── for each report whose cron matches:
           dispatch(ProcessReportJob) → Queue Worker
                                         ├── runs report SQL (INSERT ... ON CONFLICT)
                                         ├── writes to analytics.* tables
                                         ├── ShouldBeUnique (no duplicate runs)
                                         └── retries on failure (3 attempts)
     ↓
Grafana/Metabase → reads analytics.* (read-only user)
```

**Design principles:**
- `public` schema = transactional writes (app)
- `analytics` schema = read-only aggregations (dashboards)
- Events are append-only (auditable, no data loss)
- Reports use `INSERT ... ON CONFLICT DO UPDATE` (incremental upsert, idempotent)
- Each report is a PHP class with full SQL control — no rigid schema
- Reports run as queued jobs — visible in Horizon, with automatic retries

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

Run migrations and create analytics schema:

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

### 2. Create reports

Each report is a PHP class that defines its own SQL, output table, and schedule. You get full SQL control — compute activation rates, cohort retention, revenue breakdowns, or anything that needs custom joins and data from any table.

```bash
php artisan make:report ActivationRate
```

This generates `app/Reports/ActivationRateReport.php`:

```php
use Multek\BusinessMetrics\Reports\BusinessReport;

class ActivationRateReport extends BusinessReport
{
    public function table(): string
    {
        return 'analytics.activation_rate';
    }

    public function schema(): string
    {
        return <<<'SQL'
            CREATE TABLE IF NOT EXISTS analytics.activation_rate (
                cohort_week DATE PRIMARY KEY,
                signups BIGINT NOT NULL DEFAULT 0,
                activated BIGINT NOT NULL DEFAULT 0,
                activation_rate NUMERIC(5,2) NOT NULL DEFAULT 0,
                updated_at TIMESTAMPTZ DEFAULT NOW()
            )
        SQL;
    }

    public function query(): string
    {
        return <<<'SQL'
            INSERT INTO analytics.activation_rate (cohort_week, signups, activated, activation_rate)
            SELECT
                date_trunc('week', s.occurred_at)::date AS cohort_week,
                COUNT(DISTINCT s.actor_user_id) AS signups,
                COUNT(DISTINCT a.actor_user_id) AS activated,
                ROUND(
                    COUNT(DISTINCT a.actor_user_id)::numeric
                    / NULLIF(COUNT(DISTINCT s.actor_user_id), 0) * 100, 2
                ) AS activation_rate
            FROM public.business_events s
            LEFT JOIN public.business_events a
                ON a.actor_user_id = s.actor_user_id
                AND a.event_name = 'onboarding_completed'
                AND a.occurred_at BETWEEN s.occurred_at AND s.occurred_at + INTERVAL '7 days'
            WHERE s.event_name = 'user_signed_up'
                AND s.occurred_at >= NOW() - INTERVAL '3 weeks'
            GROUP BY 1
            ON CONFLICT (cohort_week) DO UPDATE SET
                signups = EXCLUDED.signups,
                activated = EXCLUDED.activated,
                activation_rate = EXCLUDED.activation_rate,
                updated_at = NOW()
        SQL;
    }

    public function schedule(): string
    {
        return '0 */6 * * *'; // every 6 hours
    }
}
```

**How incremental upsert works:**
- `WHERE occurred_at >= NOW() - INTERVAL '3 weeks'` → only queries recent data
- `ON CONFLICT ... DO UPDATE` → upserts recent rows (insert new, update existing)
- Rows older than the lookback window → untouched, stay forever
- No retention pruning by default → data accumulates over time

### 3. Register reports

```php
// config/business-metrics.php
'reports' => [
    \App\Reports\ActivationRateReport::class,
],
```

### 4. Create tables

```bash
php artisan business-metrics:create-schema   # creates analytics.* tables from each report's schema()
```

### 5. How scheduling works (zero-config)

**You don't need to register anything in your scheduler.** The package handles it automatically.

When you register a report in config and define its `schedule()` cron expression, the package's `ServiceProvider` automatically registers it with Laravel's scheduler:

```php
// This happens inside the package — you don't write this code:
$schedule->job(new ProcessReportJob($reportClass))
    ->cron($report->schedule())      // uses YOUR cron from schedule()
    ->withoutOverlapping();
```

So the full flow is:

1. You create a report class with `schedule()` returning a cron expression (e.g. `'0 */6 * * *'`)
2. You register it in `config/business-metrics.php` → `'reports'` array
3. The package reads all reports on boot, and for each one registers a scheduled job with that cron
4. Laravel's scheduler (`php artisan schedule:run`, which your server runs every minute via crontab) checks the cron and dispatches `ProcessReportJob` to the queue when it matches
5. Your queue worker picks up the job, runs the report SQL, done

**The only thing you need on your server** is the standard Laravel crontab entry (you probably already have this):

```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

And a queue worker running:

```bash
php artisan queue:work
```

### 6. Queue configuration (optional)

Configure the queue name for report jobs in `.env`:

```
BUSINESS_METRICS_REPORTS_QUEUE=analytics
```

Or leave it unset to use the default queue. Report jobs are visible in Horizon, retry automatically (3 attempts, 60s backoff), and won't run concurrently for the same report (`ShouldBeUnique`).

### 7. Run reports manually

For debugging or one-off runs:

```bash
php artisan business-metrics:reports --sync                              # run all now (sync)
php artisan business-metrics:reports --report=ActivationRateReport --sync # run one (sync)
php artisan business-metrics:reports                                      # dispatch all to queue
php artisan business-metrics:reports --report=ActivationRateReport        # dispatch one to queue
```

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
# Create analytics schema and report tables
php artisan business-metrics:create-schema

# Generate a new report class
php artisan make:report ActivationRate

# Dispatch all reports to queue
php artisan business-metrics:reports

# Dispatch a specific report to queue
php artisan business-metrics:reports --report=ActivationRateReport

# Run all reports synchronously (useful for debugging)
php artisan business-metrics:reports --sync

# Run a specific report synchronously
php artisan business-metrics:reports --report=ActivationRateReport --sync

# List registered events
php artisan business-metrics:events

# Prune old events
php artisan business-metrics:prune
php artisan business-metrics:prune --days=90
```

## BusinessReport API

Each report extends `Multek\BusinessMetrics\Reports\BusinessReport`:

| Method | Required | Description |
|--------|----------|-------------|
| `table(): string` | Yes | Target table name (e.g. `analytics.activation_rate`) |
| `schema(): string` | Yes | `CREATE TABLE IF NOT EXISTS` SQL |
| `query(): string` | Yes | `INSERT INTO ... SELECT ... ON CONFLICT` SQL |
| `schedule(): string` | Yes | Cron expression for scheduling |
| `retentionDays(): ?int` | No | Days to keep rows (`null` = keep forever) |
| `retentionColumn(): string` | No | Column for pruning (default: `updated_at`) |
| `connection(): ?string` | No | DB connection (default: package connection) |

## Grafana Queries (Examples)

Since reports produce custom tables, your Grafana queries match your report schema:

```sql
-- Activation rate over time
SELECT cohort_week AS time, activation_rate
FROM analytics.activation_rate
ORDER BY cohort_week DESC
LIMIT 12
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

The package is designed so that when you move to a warehouse, you replicate the same `business_events` table and report structure — no redesign needed.

## License

MIT
