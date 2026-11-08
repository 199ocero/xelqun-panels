<?php

namespace Xelqun\Panels\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Xelqun\Panels\Contracts\PanelUser;

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

        $appNamespace = $this->laravel->getNamespace();
        $namespace = "{$appNamespace}Providers\\Xelqun";
        $id = Str::kebab($name);

        File::ensureDirectoryExists(dirname($path));
        File::put($path, str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ id }}', '{{ name }}', '{{ resourceNamespace }}'],
            [$namespace, $class, $id, $name, str_replace('\\', '\\\\', "{$appNamespace}Xelqun\\{$name}\\Resources")],
            File::get(__DIR__.'/../../stubs/panel-provider.php.stub'),
        ));

        ServiceProvider::addProviderToBootstrapFile("{$namespace}\\{$class}");

        $this->components->info("Panel created. It will live at /{$id}.");
        $userModel = config('auth.providers.users.model');

        $this->components->bulletList(array_filter([
            $path,
            is_string($userModel) && is_subclass_of($userModel, PanelUser::class) ? null : 'Add Xelqun\Panels\Contracts\PanelUser to your user model, or nobody can sign in.',
        ]));

        return self::SUCCESS;
    }
}
