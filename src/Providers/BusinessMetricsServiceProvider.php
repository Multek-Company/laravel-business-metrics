<?php

namespace Multek\BusinessMetrics\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Multek\BusinessMetrics\Commands\CreateAnalyticsSchemaCommand;
use Multek\BusinessMetrics\Commands\ProcessRollupsCommand;
use Multek\BusinessMetrics\Commands\PruneEventsCommand;
use Multek\BusinessMetrics\Commands\ListEventsCommand;
use Multek\BusinessMetrics\Services\BusinessEventLogger;
use Multek\BusinessMetrics\Services\RollupProcessor;

class BusinessMetricsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/business-metrics.php', 'business-metrics');

        $this->app->singleton(BusinessEventLogger::class, function ($app) {
            return new BusinessEventLogger(
                config('business-metrics.connection'),
                config('business-metrics.events_table'),
                $this->resolveEvents(),
                config('business-metrics.queue'),
            );
        });

        $this->app->singleton(RollupProcessor::class, function ($app) {
            return new RollupProcessor(
                config('business-metrics.connection'),
                config('business-metrics.events_table'),
                config('business-metrics.rollups'),
                config('business-metrics.funnel_stages'),
            );
        });
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../../config/business-metrics.php' => config_path('business-metrics.php'),
        ], 'business-metrics-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../../database/migrations/' => database_path('migrations'),
        ], 'business-metrics-migrations');

        // Publish stub enum
        $this->publishes([
            __DIR__ . '/../../stubs/BusinessEventType.php.stub' => app_path('Enums/BusinessEventType.php'),
        ], 'business-metrics-enum');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateAnalyticsSchemaCommand::class,
                ProcessRollupsCommand::class,
                PruneEventsCommand::class,
                ListEventsCommand::class,
            ]);
        }

        // Register scheduled rollups
        $this->app->booted(function () {
            $this->scheduleRollups();
        });
    }

    protected function scheduleRollups(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $rollups = config('business-metrics.rollups', []);

        foreach ($rollups as $name => $rollup) {
            if ($rollup['enabled'] ?? false) {
                $schedule->command(ProcessRollupsCommand::class, ['--rollup' => $name])
                    ->cron($rollup['schedule'])
                    ->withoutOverlapping()
                    ->runInBackground();
            }
        }

        // Prune old events daily at 3 AM
        $retentionDays = config('business-metrics.events_retention_days', 365);
        if ($retentionDays > 0) {
            $schedule->command(PruneEventsCommand::class)
                ->dailyAt('03:00')
                ->withoutOverlapping();
        }
    }

    /**
     * Resolve events from config — supports array or enum class.
     */
    protected function resolveEvents(): array
    {
        $events = config('business-metrics.events', []);

        if (is_string($events) && enum_exists($events)) {
            return array_map(
                fn ($case) => $case->value,
                $events::cases()
            );
        }

        return $events;
    }
}
