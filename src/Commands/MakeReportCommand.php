<?php

namespace Multek\BusinessMetrics\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeReportCommand extends Command
{
    protected $signature = 'make:report {name : The name of the report class}';

    protected $description = 'Create a new business metrics report class';

    public function handle(Filesystem $files): int
    {
        $name = $this->argument('name');
        $className = Str::studly($name);

        if (! Str::endsWith($className, 'Report')) {
            $className .= 'Report';
        }

        $directory = app_path('Reports');
        $path = $directory . '/' . $className . '.php';

        if ($files->exists($path)) {
            $this->error("Report [{$className}] already exists!");
            return self::FAILURE;
        }

        $files->ensureDirectoryExists($directory);

        $stub = $files->get($this->stubPath());
        $stub = str_replace(
            ['{{ class }}', '{{ table }}'],
            [$className, Str::snake(Str::replaceLast('Report', '', $className))],
            $stub,
        );

        $files->put($path, $stub);

        $this->info("Report [{$className}] created successfully at app/Reports/{$className}.php");
        $this->newLine();
        $this->line("Next steps:");
        $this->line("  1. Edit the schema(), query(), and schedule() methods");
        $this->line("  2. Register it in config/business-metrics.php:");
        $this->line("     'reports' => [\\App\\Reports\\{$className}::class]");
        $this->line("  3. Run: php artisan business-metrics:create-schema");

        return self::SUCCESS;
    }

    protected function stubPath(): string
    {
        $published = resource_path('stubs/vendor/business-metrics/Report.php.stub');

        if (file_exists($published)) {
            return $published;
        }

        return __DIR__ . '/../../stubs/Report.php.stub';
    }
}
