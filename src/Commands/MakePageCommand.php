<?php

namespace Xelqun\Panels\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakePageCommand extends Command
{
    private const string CLASS_NAME = '/^[A-Z][A-Za-z0-9]*$/';

    protected $signature = 'make:xelqun-page
        {name : The class name, e.g. Reports}
        {--panel=Admin : The panel it belongs to}
        {--force : Overwrite the file if it exists}';

    protected $description = 'Create a Xelqun panel page';

    public function handle(): int
    {
        $class = (string) $this->argument('name');
        $panel = (string) $this->option('panel');

        foreach ([$class, $panel] as $name) {
            if (preg_match(self::CLASS_NAME, $name) !== 1) {
                $this->components->error("[{$name}] is not a valid class name.");

                return self::FAILURE;
            }
        }

        $path = app_path("Xelqun/{$panel}/Pages/{$class}.php");

        if (File::exists($path) && ! $this->option('force')) {
            $this->components->error("[{$path}] already exists. Use --force to overwrite it.");

            return self::FAILURE;
        }

        $namespace = $this->laravel->getNamespace()."Xelqun\\{$panel}\\Pages";

        File::ensureDirectoryExists(dirname($path));
        File::put($path, str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [$namespace, $class],
            File::get(__DIR__.'/../../stubs/page.php.stub'),
        ));

        $this->components->info('Page created.');
        $this->components->bulletList([$path, "Add {$class}::class to ->pages([...]) in your panel provider."]);

        return self::SUCCESS;
    }
}
