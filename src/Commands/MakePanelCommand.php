<?php

namespace Xelqun\Panels\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class MakePanelCommand extends Command
{
    private const string NAME = '/^[A-Z][A-Za-z0-9]*$/';

    protected $signature = 'make:xelqun-panel
        {name : The panel name, e.g. Admin}
        {--force : Overwrite the file if it exists}';

    protected $description = 'Create a Xelqun panel and register its provider';

    public function handle(): int
    {
        $name = (string) $this->argument('name');

        if (preg_match(self::NAME, $name) !== 1) {
            $this->components->error("[{$name}] is not a valid panel name.");

            return self::FAILURE;
        }

        $class = "{$name}PanelProvider";
        $path = app_path("Providers/Xelqun/{$class}.php");

        if (File::exists($path) && ! $this->option('force')) {
            $this->components->error("[{$path}] already exists. Use --force to overwrite it.");

            return self::FAILURE;
        }

        $namespace = $this->laravel->getNamespace().'Providers\\Xelqun';
        $id = Str::kebab($name);

        File::ensureDirectoryExists(dirname($path));
        File::put($path, str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ id }}'],
            [$namespace, $class, $id],
            File::get(__DIR__.'/../../stubs/panel-provider.php.stub'),
        ));

        ServiceProvider::addProviderToBootstrapFile("{$namespace}\\{$class}");

        $this->components->info("Panel created. It will live at /{$id}.");
        $this->components->bulletList([
            $path,
            'Add Xelqun\Panels\Contracts\PanelUser to your user model, or nobody can sign in.',
        ]);

        return self::SUCCESS;
    }
}
