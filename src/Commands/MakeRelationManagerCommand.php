<?php

namespace Xelqun\Panels\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Xelqun\Panels\Commands\Concerns\GuessesTitleColumn;

class MakeRelationManagerCommand extends Command
{
    use GuessesTitleColumn;

    private const string CLASS_NAME = '/^[A-Z][A-Za-z0-9]*$/';

    private const string METHOD_NAME = '/^[a-z][A-Za-z0-9]*$/';

    protected $signature = 'make:xelqun-relation-manager
        {model : The parent model, e.g. Category}
        {relationship : The relationship method on it, e.g. products}
        {--panel=Admin : The panel it belongs to}
        {--force : Overwrite the file if it exists}';

    protected $description = 'Create a Xelqun relation manager';

    public function handle(): int
    {
        $model = (string) $this->argument('model');
        $relationship = (string) $this->argument('relationship');
        $panel = (string) $this->option('panel');

        if (preg_match(self::CLASS_NAME, $model) !== 1 || preg_match(self::CLASS_NAME, $panel) !== 1 || preg_match(self::METHOD_NAME, $relationship) !== 1) {
            $this->components->error('Use a class name for the model and panel, and a method name for the relationship.');

            return self::FAILURE;
        }

        $class = Str::studly($relationship).'RelationManager';
        $path = app_path("Xelqun/{$panel}/Resources/{$model}Resource/{$class}.php");

        if (File::exists($path) && ! $this->option('force')) {
            $this->components->error("[{$path}] already exists. Use --force to overwrite it.");

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($path));
        $parentClass = "{$this->laravel->getNamespace()}Models\\{$model}";
        $related = is_subclass_of($parentClass, Model::class) && method_exists($parentClass, $relationship) ? rescue(fn (): ?Model => (new $parentClass)->{$relationship}()->getRelated(), report: false) : null;

        File::put($path, str_replace(
            ['{{ namespace }}', '{{ class }}', '{{ relationship }}', '{{ title }}'],
            ["{$this->laravel->getNamespace()}Xelqun\\{$panel}\\Resources\\{$model}Resource", $class, $relationship, $this->guessTitleColumn($related)],
            File::get(__DIR__.'/../../stubs/relation-manager.php.stub'),
        ));

        $this->components->info('Relation manager created.');
        $this->components->bulletList([
            $path,
            "Return {$class}::class from getRelations() in {$model}Resource.",
            'The related model needs a policy, or nobody can see this table.',
        ]);

        return self::SUCCESS;
    }
}
