<?php

namespace Xelqun\Panels\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeResourceCommand extends Command
{
    private const string CLASS_NAME = '/^[A-Z][A-Za-z0-9]*$/';

    protected $signature = 'make:xelqun-resource
        {model : The model it manages, e.g. Product}
        {--panel=Admin : The panel it belongs to}
        {--simple : Keep everything on one page, with create and edit in modals}
        {--force : Overwrite the file if it exists}';

    protected $description = 'Create a Xelqun resource and a policy for its model';

    public function handle(): int
    {
        $model = (string) $this->argument('model');
        $panel = (string) $this->option('panel');

        foreach ([$model, $panel] as $name) {
            if (preg_match(self::CLASS_NAME, $name) !== 1) {
                $this->components->error("[{$name}] is not a valid class name.");

                return self::FAILURE;
            }
        }

        $class = "{$model}Resource";
        $path = app_path("Xelqun/{$panel}/Resources/{$class}.php");

        if (File::exists($path) && ! $this->option('force')) {
            $this->components->error("[{$path}] already exists. Use --force to overwrite it.");

            return self::FAILURE;
        }

        $namespace = $this->laravel->getNamespace();

        File::ensureDirectoryExists(dirname($path));
        File::put($path, str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ modelNamespace }}', '{{ model }}'],
            ["{$namespace}Xelqun\\{$panel}\\Resources", $class, "{$namespace}Models", $model],
            File::get(__DIR__.'/../../stubs/'.($this->option('simple') ? 'resource-simple.php.stub' : 'resource.php.stub')),
        ));

        // Without a policy nobody can open the resource, so it comes with one that refuses until it's filled in.
        if (! File::exists(app_path("Policies/{$model}Policy.php"))) {
            $this->callSilently('make:policy', ['name' => "{$model}Policy", '--model' => $model]);
        }

        $this->components->info('Resource created.');
        $this->components->bulletList([
            $path,
            "Add {$class}::class to ->resources([...]) if your panel doesn't use discoverResources().",
            "Fill in app/Policies/{$model}Policy.php. Until then, nobody can open this resource.",
        ]);

        return self::SUCCESS;
    }
}
