<?php

namespace Xelqun\Panels\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeWidgetCommand extends Command
{
    private const string CLASS_NAME = '/^[A-Z][A-Za-z0-9]*$/';

    protected $signature = 'make:xelqun-widget
        {name : The widget class, e.g. SalesChart}
        {--panel=Admin : The panel it belongs to}
        {--stats : Make a stats overview, not a chart}
        {--force : Overwrite the file if it exists}';

    protected $description = 'Create a Xelqun dashboard widget';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $panel = (string) $this->option('panel');

        foreach ([$name, $panel] as $value) {
            if (preg_match(self::CLASS_NAME, $value) !== 1) {
                $this->components->error("[{$value}] is not a valid class name.");

                return self::FAILURE;
            }
        }

        $path = app_path("Xelqun/{$panel}/Widgets/{$name}.php");

        if (File::exists($path) && ! $this->option('force')) {
            $this->components->error("[{$path}] already exists. Use --force to overwrite it.");

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ heading }}'],
            ["{$this->laravel->getNamespace()}Xelqun\\{$panel}\\Widgets", $name, Str::headline($name)],
            File::get(__DIR__.'/../../stubs/'.($this->option('stats') ? 'stats-widget.php.stub' : 'chart-widget.php.stub')),
        ));

        $this->components->info('Widget created.');
        $this->components->bulletList([$path, "Add {$name}::class to ->widgets([...]) in your panel provider."]);

        return self::SUCCESS;
    }
}
