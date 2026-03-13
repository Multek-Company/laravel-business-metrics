# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Package Overview

Laravel package for server-side business event tracking with flexible reports and analytics schema. Designed to feed BI tools like Grafana and Metabase.

## Development Commands

```bash
# Install dependencies
composer install

# Run all tests
./vendor/bin/pest

# Run a single test file
./vendor/bin/pest tests/Feature/SomeTest.php

# Run a specific test by name
./vendor/bin/pest --filter "test name"

# Code style fix
./vendor/bin/pint
```

## Directory Structure

```
src/
├── Commands/              # Artisan commands
├── Facades/               # BusinessEvent facade
├── Jobs/                  # Async event processing
├── Models/                # Eloquent models (events, reports)
├── Providers/             # BusinessMetricsServiceProvider
├── Reports/               # Report definitions
├── Services/              # Business logic
└── Traits/                # Model traits

config/                    # Package configuration
database/migrations/       # Database schema
tests/                     # Test suite
```

## Architecture

- **Namespace**: `Multek\BusinessMetrics`
- **ServiceProvider**: `Multek\BusinessMetrics\Providers\BusinessMetricsServiceProvider`
- **Facade**: `Multek\BusinessMetrics\Facades\BusinessEvent`
- Uses Orchestra Testbench for testing

## Testing Guidelines

- Use Pest framework with Orchestra Testbench
- Test event tracking, report generation, and query building
