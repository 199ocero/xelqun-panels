<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

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
            ->and(File::get("{$this->appPath}/resources/js/pages/xelqun/PanelAuth.vue"))->toContain('export default XqPanelAuthPage')
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
    it('leaves out the PanelUser step when the user model has it', function (): void {
        $this->artisan('make:xelqun-panel', ['name' => 'Staff'])
            ->doesntExpectOutputToContain('PanelUser')
            ->assertSuccessful();
    });

    it('creates a provider and registers it', function (): void {
        config(['auth.providers.users.model' => User::class]);

        $this->artisan('make:xelqun-panel', ['name' => 'BackOffice'])
            ->expectsOutputToContain('PanelUser')
            ->assertSuccessful();

        expect(File::get("{$this->appPath}/app/Providers/Xelqun/BackOfficePanelProvider.php"))
            ->toContain("->id('back-office')")
            ->toContain("->path('back-office')")
            ->toContain('->login()')
            ->toContain("->discoverResources(in: app_path('Xelqun/BackOffice/Resources'), for: 'App\\\\Xelqun\\\\BackOffice\\\\Resources')")
            ->toContain('->pages([Dashboard::class])')
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

describe('make:xelqun-resource', function () {
    it('creates a resource and a policy', function (): void {
        $this->artisan('make:xelqun-resource', ['model' => 'Product', '--panel' => 'Staff'])
            ->expectsOutputToContain('Add ProductResource::class')
            ->assertSuccessful();

        expect(File::get("{$this->appPath}/app/Xelqun/Staff/Resources/ProductResource.php"))
            ->toContain('namespace App\Xelqun\Staff\Resources;')
            ->toContain('protected static string $model = Product::class;');
    });

    it('starts from a column the model\'s table has, and falls back to name', function (): void {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('total');
            $table->string('number');
        });
        eval('namespace App\\Models; class Invoice extends \\Illuminate\\Database\\Eloquent\\Model {}');

        $this->artisan('make:xelqun-resource', ['model' => 'Invoice'])->assertSuccessful();
        $this->artisan('make:xelqun-resource', ['model' => 'Ghost'])->assertSuccessful();

        expect(File::get("{$this->appPath}/app/Xelqun/Admin/Resources/InvoiceResource.php"))
            ->toContain("TextInput::make('number')")
            ->toContain("TextColumn::make('number')")
            ->toContain("protected static ?string \$recordTitleAttribute = 'number';")
            ->and(File::get("{$this->appPath}/app/Xelqun/Admin/Resources/GhostResource.php"))->toContain("TextInput::make('name')");
    });

    it('makes a one-page resource', function (): void {
        $this->artisan('make:xelqun-resource', ['model' => 'Tag', '--simple' => true])->assertSuccessful();

        expect(File::get("{$this->appPath}/app/Xelqun/Admin/Resources/TagResource.php"))
            ->toContain("return ['index' => ManageRecords::class];");
    });

    it('refuses a bad name, and keeps what is there unless told otherwise', function (): void {
        $this->artisan('make:xelqun-resource', ['model' => 'not-a-class'])->assertFailed();

        $this->artisan('make:xelqun-resource', ['model' => 'Product'])->assertSuccessful();
        $this->artisan('make:xelqun-resource', ['model' => 'Product'])->assertFailed();
        $this->artisan('make:xelqun-resource', ['model' => 'Product', '--force' => true])->assertSuccessful();
    });
});

describe('make:xelqun-relation-manager', function () {
    it('creates a manager beside its resource', function (): void {
        $this->artisan('make:xelqun-relation-manager', ['model' => 'Category', 'relationship' => 'products'])
            ->expectsOutputToContain('ProductsRelationManager::class')
            ->assertSuccessful();

        expect(File::get("{$this->appPath}/app/Xelqun/Admin/Resources/CategoryResource/ProductsRelationManager.php"))
            ->toContain('namespace App\Xelqun\Admin\Resources\CategoryResource;')
            ->toContain("protected static string \$relationship = 'products';");
    });

    it('refuses a bad name, and keeps what is there unless told otherwise', function (): void {
        $this->artisan('make:xelqun-relation-manager', ['model' => 'Category', 'relationship' => 'Not-a-method'])->assertFailed();

        $this->artisan('make:xelqun-relation-manager', ['model' => 'Category', 'relationship' => 'products'])->assertSuccessful();
        $this->artisan('make:xelqun-relation-manager', ['model' => 'Category', 'relationship' => 'products'])->assertFailed();
        $this->artisan('make:xelqun-relation-manager', ['model' => 'Category', 'relationship' => 'products', '--force' => true])->assertSuccessful();
    });
});

describe('make:xelqun-widget', function () {
    it('creates a chart, or stats with --stats', function (): void {
        $this->artisan('make:xelqun-widget', ['name' => 'SalesChart'])->expectsOutputToContain('SalesChart::class')->assertSuccessful();
        $this->artisan('make:xelqun-widget', ['name' => 'Totals', '--stats' => true, '--panel' => 'Staff'])->assertSuccessful();

        expect(File::get("{$this->appPath}/app/Xelqun/Admin/Widgets/SalesChart.php"))->toContain('extends ChartWidget')
            ->and(File::get("{$this->appPath}/app/Xelqun/Staff/Widgets/Totals.php"))->toContain('extends StatsOverview');
    });

    it('refuses a bad name, and keeps what is there unless told otherwise', function (): void {
        $this->artisan('make:xelqun-widget', ['name' => 'not-a-class'])->assertFailed();

        $this->artisan('make:xelqun-widget', ['name' => 'Totals'])->assertSuccessful();
        $this->artisan('make:xelqun-widget', ['name' => 'Totals'])->assertFailed();
    });
});
