<?php

namespace Multek\BusinessMetrics\Tests;

use Multek\BusinessMetrics\Providers\BusinessMetricsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            BusinessMetricsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('business-metrics.connection', 'testing');
        $app['config']->set('business-metrics.events_table', 'business_events');
        $app['config']->set('business-metrics.analytics_schema', 'analytics');
        $app['config']->set('business-metrics.reports', []);
    }
}
