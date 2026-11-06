<?php

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->appPath = sys_get_temp_dir().'/xelqun-panels-'.uniqid();

    File::ensureDirectoryExists("{$this->appPath}/resources/js");
    File::ensureDirectoryExists("{$this->appPath}/resources/css");
    File::ensureDirectoryExists("{$this->appPath}/bootstrap");
    File::put("{$this->appPath}/composer.json", json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]));
    File::put("{$this->appPath}/bootstrap/providers.php", "<?php\n\nreturn [\n    App\\Providers\\AppServiceProvider::class,\n];\n");

    $this->originalBasePath = $this->app->basePath();
    $this->app->setBasePath($this->appPath);
});

afterEach(function (): void {
    $this->app->setBasePath($this->originalBasePath);
    File::deleteDirectory($this->appPath);
});

describe('xelqun-panels:install', function () {
    beforeEach(function (): void {
        File::ensureDirectoryExists("{$this->appPath}/vendor/xelqun/panels");
        File::copy(__DIR__.'/../../composer.json', "{$this->appPath}/vendor/xelqun/panels/composer.json");
        File::put("{$this->appPath}/resources/css/app.css", "@import 'tailwindcss';\n");
        File::put("{$this->appPath}/resources/js/app.ts", <<<'TS'
            import { createInertiaApp } from '@inertiajs/vue3';

            void createInertiaApp({
                title: (title) => title,
            });

            TS);
    });

    it('connects the app\'s frontend', function (): void {
        $this->artisan('xelqun-panels:install', ['--without-node' => true])->assertSuccessful();

        expect(File::get("{$this->appPath}/resources/js/pages/xelqun/Panel.vue"))->toContain('export default XqPanelPage')
            ->and(File::get("{$this->appPath}/resources/css/app.css"))
            ->toContain('@xelqun/forms/style.css')
            ->toContain('@xelqun/tables/style.css')
            ->toContain('@xelqun/panels/style.css')
            ->and(File::get("{$this->appPath}/resources/js/app.ts"))
            ->toContain('createXelqunForms')
            ->toContain('panelsPlugin()');
    });

    it('leaves a page file the app changed, and can run twice', function (): void {
        $path = "{$this->appPath}/resources/js/pages/xelqun/Panel.vue";
        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'changed');

        $this->artisan('xelqun-panels:install', ['--without-node' => true])->assertSuccessful();
        $this->artisan('xelqun-panels:install', ['--without-node' => true])->assertSuccessful();

        expect(File::get($path))->toBe('changed')
            ->and(substr_count(File::get("{$this->appPath}/resources/js/app.ts"), 'panelsPlugin()'))->toBe(1);
    });
});

describe('make:xelqun-panel', function () {
    it('creates a provider and registers it', function (): void {
        $this->artisan('make:xelqun-panel', ['name' => 'BackOffice'])->assertSuccessful();

        expect(File::get("{$this->appPath}/app/Providers/Xelqun/BackOfficePanelProvider.php"))
            ->toContain("->id('back-office')")
            ->toContain("->path('back-office')")
            ->and(File::get("{$this->appPath}/bootstrap/providers.php"))
            ->toContain('App\Providers\Xelqun\BackOfficePanelProvider::class');
    });

    it('refuses a bad name, and keeps what is there unless told otherwise', function (): void {
        $this->artisan('make:xelqun-panel', ['name' => 'not-a-class'])->assertFailed();

        $this->artisan('make:xelqun-panel', ['name' => 'Admin'])->assertSuccessful();
        $this->artisan('make:xelqun-panel', ['name' => 'Admin'])->assertFailed();
        $this->artisan('make:xelqun-panel', ['name' => 'Admin', '--force' => true])->assertSuccessful();
    });
});

describe('make:xelqun-page', function () {
    it('creates a page for a panel', function (): void {
        $this->artisan('make:xelqun-page', ['name' => 'Reports', '--panel' => 'Staff'])
            ->expectsOutputToContain('Add Reports::class')
            ->assertSuccessful();

        expect(File::get("{$this->appPath}/app/Xelqun/Staff/Pages/Reports.php"))
            ->toContain('namespace App\Xelqun\Staff\Pages;')
            ->toContain('class Reports extends Page');
    });

    it('refuses a bad name, and keeps what is there unless told otherwise', function (): void {
        $this->artisan('make:xelqun-page', ['name' => 'not-a-class'])->assertFailed();

        $this->artisan('make:xelqun-page', ['name' => 'Reports'])->assertSuccessful();
        $this->artisan('make:xelqun-page', ['name' => 'Reports'])->assertFailed();
        $this->artisan('make:xelqun-page', ['name' => 'Reports', '--force' => true])->assertSuccessful();
    });
});
