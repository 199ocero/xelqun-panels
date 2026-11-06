<?php

namespace Xelqun\Panels\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Xelqun\Forms\Commands\Concerns\EditsHostApp;
use Xelqun\Panels\Panels;

class InstallCommand extends Command
{
    use EditsHostApp;

    private const string NPM_PACKAGE = '@xelqun/panels';

    private const string STYLESHEET = '@xelqun/panels/style.css';

    protected $signature = 'xelqun-panels:install
        {--config : Publish the config file}
        {--without-node : Do not install the npm package}';

    protected $description = 'Install Xelqun Panels and connect it to the app\'s frontend';

    public function handle(): int
    {
        if (! $this->option('without-node') && ! $this->installNodePackage(self::NPM_PACKAGE.'@'.Panels::VERSION)) {
            return self::FAILURE;
        }

        $this->call('xelqun-forms:install', ['--without-node' => true]);
        $this->call('xelqun-tables:install', ['--without-node' => true]);
        $this->writePageStub('Panel', 'XqPanelPage');
        $this->writePageStub('PanelAuth', 'XqPanelAuthPage');

        $manualStep = $this->importStylesheet(self::STYLESHEET);

        if ($this->option('config')) {
            $this->callSilently('vendor:publish', ['--tag' => 'xelqun-panels-config']);
        }

        $this->call('xelqun-forms:plugin', ['package' => 'xelqun/panels', '--without-node' => true]);

        if ($manualStep !== null) {
            $this->components->warn('Finish this step by hand:');
            $this->components->bulletList([$manualStep]);

            return self::SUCCESS;
        }

        $this->components->info('Xelqun Panels is installed. Run `php artisan make:xelqun-panel Admin` to create a panel.');

        return self::SUCCESS;
    }

    /**
     * The Vite plugin only finds pages in the app, so a page of the package needs a file there.
     */
    private function writePageStub(string $file, string $component): void
    {
        $path = resource_path("js/pages/xelqun/{$file}.vue");

        if (File::exists($path)) {
            return;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, str_replace('{{ component }}', $component, File::get(__DIR__.'/../../stubs/page.vue.stub')));
    }
}
