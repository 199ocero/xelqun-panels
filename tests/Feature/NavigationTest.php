<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Xelqun\Panels\Contracts\Plugin;
use Xelqun\Panels\Exceptions\PanelDefinitionException;
use Xelqun\Panels\Navigation\NavigationGroup;
use Xelqun\Panels\Navigation\NavigationItem;
use Xelqun\Panels\Navigation\SidebarCard;
use Xelqun\Panels\Pages\Page;
use Xelqun\Panels\Panel;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Support\Colors\Color;
use Xelqun\Panels\Tests\Fixtures\Clusters\Billing;
use Xelqun\Panels\Tests\Fixtures\Clusters\ClusteredTaskResource;
use Xelqun\Panels\Tests\Fixtures\Note;
use Xelqun\Panels\Tests\Fixtures\Resources\RelatedPolicy;
use Xelqun\Panels\Tests\Fixtures\Resources\TaskPolicy;
use Xelqun\Panels\Tests\Fixtures\Resources\TaskResource;
use Xelqun\Panels\Tests\Fixtures\Task;
use Xelqun\Panels\View\RenderHook;

class FirstPage extends Page
{
    protected static ?string $navigationIcon = 'house';

    protected static int $navigationSort = 2;
}

class SecondPage extends Page
{
    protected static ?string $navigationGroup = 'Sales';

    public static function getNavigationBadge(): int
    {
        return 3;
    }
}

class ThirdPage extends Page
{
    protected static ?string $navigationGroup = 'Catalog';
}

class FourthPage extends Page
{
    protected static ?string $navigationLabel = 'Short';

    protected static int $navigationSort = 1;
}

class HiddenPage extends Page
{
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}

class DeniedPage extends Page
{
    public static function canAccess(): bool
    {
        return false;
    }
}

function panelWithPages(): Panel
{
    return Panel::make()->id('nav')->path('nav')->pages([
        FirstPage::class, SecondPage::class, ThirdPage::class, FourthPage::class, HiddenPage::class, DeniedPage::class,
    ]);
}

function defineNavigationRoutes(Panel $panel): void
{
    foreach ($panel->getPages() as $page) {
        Route::get("nav/{$page::getSlug()}", fn () => null)->name($panel->routeName($page::getRouteName()));
    }

    app('router')->getRoutes()->refreshNameLookups();
}

beforeEach(function () {
    $this->signIn();
});

it('orders groups by the panel, with ungrouped links first and unknown groups last', function () {
    $panel = panelWithPages()->navigationGroups(['Catalog', 'Reports'])
        ->navigationItems([NavigationItem::make('Docs')->url('https://example.com')->group('Help')]);
    defineNavigationRoutes($panel);

    expect(array_column($panel->getNavigation(), 'label'))->toBe([null, 'Catalog', 'Sales', 'Help']);
});

it('sorts links, and leaves out pages the user can\'t reach', function () {
    $panel = panelWithPages();
    defineNavigationRoutes($panel);

    $labels = array_column($panel->getNavigation()[0]['items'], 'label');

    expect($labels)->toBe(['Short', 'First Page']);
});

it('sends a page\'s icon, url and badge', function () {
    $panel = panelWithPages();
    defineNavigationRoutes($panel);
    $navigation = $panel->getNavigation();

    expect($navigation[0]['items'][1])->toMatchArray(['icon' => 'house', 'url' => url('nav/first-page'), 'badge' => null, 'active' => false])
        ->and($navigation[1]['items'][0]['badge'])->toBe('3');
});

it('marks the page being viewed', function () {
    $items = collect($this->get('/admin/reports')->viewData('page')['props']['xelqunPanel']['navigation'][0]['items']);

    expect($items->firstWhere('label', 'Reports')['active'])->toBeTrue()
        ->and($items->firstWhere('label', 'Home')['active'])->toBeFalse();
});

it('works out a closure badge and url when the page renders', function () {
    $count = 0;
    $item = NavigationItem::make('Orders')
        ->url(fn (): string => '/orders')
        ->badge(function () use (&$count): int {
            return ++$count;
        });

    expect($count)->toBe(0)
        ->and($item->toArray())->toMatchArray(['badge' => '1', 'url' => '/orders']);
});

it('drops an unsafe link', function (string $url) {
    expect(NavigationItem::make('Docs')->url($url)->toArray()['url'])->toBeNull()
        ->and(SidebarCard::make('Credits')->link('Upgrade', $url)->toArray()['link'])->toBeNull();
})->with(['javascript:alert(1)', 'data:text/html,x', '//evil.example']);

it('keeps a sidebar meter between empty and full', function (float $given, float $sent) {
    expect(SidebarCard::make('Credits')->progress($given)->toArray()['progress'])->toBe($sent);
})->with([[-1.0, 0.0], [0.4, 0.4], [7.0, 1.0]]);

it('runs the sidebar card for each page, and copes with having none', function () {
    $panel = app(Panels::class)->get('admin');
    $panel->sidebarFooter(fn (): ?SidebarCard => null);

    expect($panel->toShell()['sidebarFooter'])->toBeNull();

    $panel->sidebarFooter(fn (): SidebarCard => SidebarCard::make('Credits'));

    expect($panel->toShell()['sidebarFooter']['title'])->toBe('Credits');
});

describe('theme', function () {
    it('puts the panel\'s color and font on the page before it paints', function () {
        app(Panels::class)->get('admin')->colors(['primary' => '#ff0088'])->font('DM Sans');

        $this->get('/admin')
            ->assertSee("--xq-panel-font: 'DM Sans'", false)
            ->assertSee('--xq-panel-primary: #ff0088', false)
            ->assertSee('family=dm-sans:', false);
    });

    it('sends the same to the layout, for a visit that doesn\'t load the page', function () {
        app(Panels::class)->get('admin')->colors(['primary' => '#ff0088']);

        $this->get('/admin')->assertInertia(fn (Assert $page) => $page
            ->where('xelqunPanel.theme.font', 'Inter')
            ->where('xelqunPanel.theme.colors.--xq-panel-primary', '#ff0088'));
    });

    it('refuses a color that could break out of a style attribute', function (array $colors) {
        Panel::make()->colors($colors);
    })->throws(PanelDefinitionException::class)->with([
        'css' => [['primary' => 'red; background: url(evil)']],
        'quote' => [['primary' => '#000"><script>']],
        'other key' => [['accent' => '#000000']],
        'too short for a color' => [['primary' => '#0071e']],
    ]);

    it('takes every length of hex color', function (string $color) {
        expect(Panel::make()->colors(['primary' => $color])->getThemeVariables()['--xq-panel-primary'])->toBe($color);
    })->with(['#07e', '#07ef', '#0071e3', '#0071E3ff']);

    it('refuses a font that could break out of a style attribute', function () {
        Panel::make()->font("Inter'; color: red");
    })->throws(PanelDefinitionException::class);
});

it('sends the page\'s heading parts', function () {
    $this->get('/admin')->assertInertia(fn (Assert $page) => $page
        ->where('page.eyebrow', null)
        ->where('page.subheading', null)
        ->where('page.breadcrumbs', [['label' => 'Home']]));
});

describe('colors', function () {
    it('uses Filament\'s semantic colors until told otherwise', function () {
        expect(Panel::make()->getThemeVariables())->toMatchArray([
            '--xq-panel-danger' => Color::Red->value,
            '--xq-panel-gray' => Color::Zinc->value,
            '--xq-panel-info' => Color::Blue->value,
            '--xq-panel-success' => Color::Green->value,
            '--xq-panel-warning' => Color::Amber->value,
        ]);
    });

    it('sends a solid fill and its text with the primary', function () {
        expect(Panel::make()->colors(['primary' => Color::Emerald])->getThemeVariables())->toMatchArray([
            '--xq-panel-primary' => '#059669',
            '--xq-panel-primary-foreground' => '#ffffff',
        ])->toHaveKey('--xq-panel-primary-fill');
    });

    it('changes only the colors it names, from a Color or a hex value', function () {
        expect(Panel::make()->colors(['primary' => Color::Amber, 'danger' => '#ff0000'])->getThemeVariables())->toMatchArray([
            '--xq-panel-primary' => '#d97706',
            '--xq-panel-danger' => '#ff0000',
            '--xq-panel-gray' => '#52525b',
        ]);
    });

    it('keeps white text on most colors, deepening the fill until it passes', function (Color $color) {
        $solid = Color::solidFor($color->value);

        expect($solid['text'])->toBe('#ffffff')
            ->and(Color::contrast($solid['fill'], $solid['text']))->toBeGreaterThanOrEqual(4.5);
    })->with([Color::Emerald, Color::Green, Color::Teal, Color::Cyan, Color::Sky, Color::Orange, Color::Red, Color::Blue, Color::Violet, Color::Zinc]);

    it('leaves a color alone when white already passes', function () {
        expect(Color::solidFor(Color::Indigo->value)['fill'])->toBe(Color::Indigo->value)
            ->and(Color::solidFor('#0071e3')['fill'])->toBe('#0071e3');
    });

    it('never puts black on a saturated color', function () {
        foreach (Color::cases() as $color) {
            expect(Color::solidFor($color->value)['text'])->not->toBe('#000000');
        }
    });

    it('goes bright with dark text in its own hue for the colors that turn brown', function (Color $color) {
        $solid = Color::solidFor($color->value);

        expect($solid['text'])->not->toBe('#ffffff')
            ->and(Color::contrast($solid['fill'], '#ffffff'))->toBeLessThan(2.5)
            ->and(Color::contrast($solid['fill'], $solid['text']))->toBeGreaterThanOrEqual(7.0);
    })->with([Color::Amber, Color::Yellow, Color::Lime]);

    it('meets WCAG AA on every color of the palette, and on any other', function () {
        $colors = [...array_column(Color::cases(), 'value'), '#777777', '#7f7f7f', '#ffffff', '#000000', '#00ff00', '#ffff00', '#00ffff', '#ff00ff', '#ff8800', '#abc', '#ffff00ff'];

        foreach ($colors as $color) {
            $solid = Color::solidFor($color);

            expect(Color::contrast($solid['fill'], $solid['text']))->toBeGreaterThanOrEqual(4.5);
        }
    });

    it('measures contrast the way WCAG does', function () {
        expect(round(Color::contrast('#000000', '#ffffff'), 2))->toBe(21.0)
            ->and(round(Color::contrast('#777777', '#ffffff'), 2))->toBe(4.48);
    });

    it('reads a closure on each request, and still checks it', function () {
        $color = Color::Rose;
        $panel = Panel::make()->colors(function () use (&$color): array {
            return ['primary' => $color];
        });

        expect($panel->getThemeVariables()['--xq-panel-primary'])->toBe('#e11d48');

        $color = 'red; background: url(evil)';

        expect(fn () => $panel->getThemeVariables())->toThrow(PanelDefinitionException::class);
    });

    it('refuses anything else', function (array $colors) {
        expect(fn () => Panel::make()->colors($colors))->toThrow(PanelDefinitionException::class);
    })->with([
        'an unknown name' => [['accent' => Color::Blue]],
        'not a color' => [['primary' => 'red']],
        'css injection' => [['primary' => '#fff; background: red']],
        'a number' => [['primary' => 123]],
    ]);

    it('finds a case by name for a saved setting', function () {
        expect(Color::fromName('Amber'))->toBe(Color::Amber)
            ->and(Color::fromName('Nope'))->toBeNull()
            ->and(Color::fromName(null))->toBeNull()
            ->and(Color::options())->toHaveKey('Amber', 'Amber');
    });
});

describe('plugins', function () {
    it('registers at once, and refuses the same one twice', function () {
        $plugin = new class implements Plugin
        {
            public function getId(): string
            {
                return 'blog';
            }

            public function register(Panel $panel): void
            {
                $panel->pages([FirstPage::class]);
            }

            public function boot(Panel $panel): void {}
        };

        $panel = Panel::make()->plugin($plugin);

        expect($panel->getPages())->toBe([FirstPage::class])
            ->and($panel->getPlugins())->toHaveKey('blog')
            ->and(fn () => $panel->plugin($plugin))->toThrow(PanelDefinitionException::class);
    });
});

describe('render hooks', function () {
    it('sends a hook to the shell, with its props', function () {
        auth('admin')->logout();
        auth('web')->logout();
        $panel = Panel::make()->id('hooks')->path('hooks')->renderHook(RenderHook::AuthFormAfter, 'acme/legal', fn (): array => ['year' => 2026]);
        app(Panels::class)->enter($panel);

        expect((array) $panel->toShell()['hooks'])->toBe(['auth-form.after' => [['component' => 'acme/legal', 'props' => ['year' => 2026]]]]);
    });

    it('keeps a hook inside the panel from a guest', function () {
        auth('admin')->logout();
        auth('web')->logout();
        $panel = Panel::make()->id('hooks')->path('hooks')->renderHook(RenderHook::ContentStart, 'acme/secret', fn () => throw new RuntimeException('Ran for a guest'));
        app(Panels::class)->enter($panel);

        expect((array) $panel->toShell()['hooks'])->toBe([]);
    });
});

it('sends the shell its text, in the app\'s language', function () {
    app('translator')->addLines(['shell.Search' => 'Buscar'], 'es', 'xelqun-panels');
    app()->setLocale('es');

    $this->get('/admin')->assertInertia(fn (Assert $page) => $page->where('xelqunPanel.messages.Search', 'Buscar'));
});

it('can move the navigation to the top', function () {
    app(Panels::class)->get('admin')->topNavigation();

    $this->get('/admin')->assertInertia(fn (Assert $page) => $page->where('xelqunPanel.topNavigation', true));

    app(Panels::class)->get('admin')->topNavigation(false);
});

describe('clusters', function () {
    it('puts its members behind one link, to the first one', function () {
        $this->get('/admin')->assertInertia(fn (Assert $page) => $page->where('xelqunPanel.navigation', function ($groups): bool {
            $items = collect($groups)->flatMap(fn (array $group): array => $group['items'])->keyBy('label');

            return $items->has('Settings')
                && $items->get('Settings')['url'] === url('/admin/settings/billing')
                && ! $items->has('Billing')
                && ! $items->has('Team');
        }));
    });

    it('links between the members, and marks the cluster as where you are', function () {
        $this->get('/admin/settings/billing')->assertInertia(fn (Assert $page) => $page
            ->where('page.subNavigation.0.label', 'Billing')
            ->where('page.subNavigation.0.active', true)
            ->where('page.subNavigation.1.label', 'Team')
            ->where('xelqunPanel.navigation', fn ($groups): bool => collect($groups)->flatMap(fn (array $group): array => $group['items'])->firstWhere('label', 'Settings')['active']));
    });

    it('names the cluster in the breadcrumbs', function () {
        $this->get('/admin/settings/billing')->assertInertia(fn (Assert $page) => $page->where('page.breadcrumbs.0.label', 'Settings'));
    });

    it('leaves out a member the user can\'t reach', function () {
        config(['testing.team_settings' => false]);

        $this->get('/admin/settings/billing')->assertInertia(fn (Assert $page) => $page->has('page.subNavigation', 1));
    });

    it('puts its members\' URLs under its slug, a resource\'s too', function () {
        Gate::policy(Task::class, TaskPolicy::class);
        TaskPolicy::$denied = [];

        expect(Billing::getUrl(panel: app(Panels::class)->get('admin')))->toBe(url('/admin/settings/billing'))
            ->and(ClusteredTaskResource::getUrl())->toBe(url('/admin/settings/tasks'))
            ->and(TaskResource::getUrl())->toBe(url('/admin/tasks'))
            // Dots, not slashes, because a host's Wayfinder turns route names into TypeScript keys.
            ->and(Billing::getRouteName())->toBe('pages.settings.billing')
            ->and(ClusteredTaskResource::getRouteBaseName())->toBe('resources.settings.tasks');

        $this->get('/admin/billing')->assertNotFound();
        $this->get('/admin/settings/tasks')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('page.breadcrumbs.0.label', 'Settings')
            ->where('page.subNavigation.2.label', 'Tasks')
            ->where('page.subNavigation.2.active', true));
    });

    it('refuses a page or resource whose slug is a cluster\'s', function () {
        $page = new class extends Page
        {
            protected static ?string $slug = 'settings';
        };

        panelWithPages()->pages([Billing::class, $page::class])->assertClusterSlugsAreFree();
    })->throws(PanelDefinitionException::class, 'both use the slug [settings]');

    it('takes a resource\'s own navigation label', function () {
        $resource = new class extends TaskResource
        {
            protected static ?string $navigationLabel = 'To-dos';
        };

        expect($resource::getNavigationLabel())->toBe('To-dos')
            ->and(TaskResource::getNavigationLabel())->toBe('Tasks');
    });

    it('has no sub navigation outside a cluster', function () {
        $this->get('/admin')->assertInertia(fn (Assert $page) => $page->where('page.subNavigation', []));
    });
});

describe('a singular resource', function () {
    beforeEach(function () {
        Gate::policy(Note::class, RelatedPolicy::class);
        RelatedPolicy::$denied = [];
    });

    it('starts with a new record, then keeps editing the one record', function () {
        $this->get('/admin/site')->assertOk();

        $this->put('/admin/site', ['data' => ['name' => 'First']])->assertRedirect('/admin/site');
        $this->put('/admin/site', ['data' => ['name' => 'Second']])->assertRedirect('/admin/site');

        expect(Note::pluck('name')->all())->toBe(['Second']);
    });

    it('has no create page or list of its own', function () {
        $this->get('/admin/site/create')->assertNotFound();
    });

    it('needs create in the policy until the record exists', function () {
        RelatedPolicy::$denied = ['create'];

        $this->get('/admin/site')->assertForbidden();
        $this->put('/admin/site', ['data' => ['name' => 'Taken']])->assertForbidden();

        expect(Note::count())->toBe(0);
    });

    it('needs update in the policy once it does', function () {
        Note::create(['name' => 'Site']);
        RelatedPolicy::$denied = ['update'];

        $this->get('/admin/site')->assertForbidden();
        $this->put('/admin/site', ['data' => ['name' => 'Taken']])->assertForbidden();

        expect(Note::sole()->name)->toBe('Site');
    });
});

describe('panel settings', function () {
    function shellFor(callable $configure): array
    {
        $panel = Panel::make()->id('settings')->path('settings');
        $configure($panel);
        auth('admin')->logout();
        auth('web')->logout();
        app(Panels::class)->enter($panel);

        return $panel->toShell();
    }

    it('sends the logo, and drops an unsafe one', function () {
        $shell = shellFor(fn (Panel $panel) => $panel->brandLogo('/logo.svg')->darkModeBrandLogo(fn (): string => 'javascript:alert(1)')->brandLogoHeight('2rem'));

        expect($shell['brand'])->toMatchArray(['logo' => '/logo.svg', 'darkLogo' => null, 'logoHeight' => '2rem']);
    });

    it('sets the content width on the page', function () {
        expect(Panel::make()->maxContentWidth('full')->getThemeVariables()['--xq-panel-content-width'])->toBe('100%');
    });

    it('refuses what would end up in a style attribute unchecked', function (Closure $configure) {
        expect(fn () => $configure(Panel::make()))->toThrow(PanelDefinitionException::class);
    })->with([
        'width' => fn (Panel $panel) => $panel->maxContentWidth('huge; color: red'),
        'logo height' => fn (Panel $panel) => $panel->brandLogoHeight('2rem; color: red'),
        'theme' => fn (Panel $panel) => $panel->defaultThemeMode('blue'),
    ]);

    it('can turn the dark theme off, or make it the only one', function () {
        expect(Panel::make()->getForcedThemeMode())->toBeNull()
            ->and(Panel::make()->darkMode(false)->getForcedThemeMode())->toBe('light')
            ->and(Panel::make()->darkMode(isForced: true)->getForcedThemeMode())->toBe('dark');
    });

    it('sends a user menu item only to someone inside', function () {
        $shell = shellFor(fn (Panel $panel) => $panel->userMenuItems([NavigationItem::make('Billing')->url('/billing')]));

        expect($shell['userMenuItems'])->toBe([]);
    });

    it('starts with animations on, unless told otherwise', function () {
        expect(shellFor(fn (Panel $panel) => $panel)['theme']['animations'])->toBeTrue()
            ->and(shellFor(fn (Panel $panel) => $panel->animations(false))['theme']['animations'])->toBeFalse();
    });

    it('can turn search and breadcrumbs off', function () {
        $shell = shellFor(fn (Panel $panel) => $panel->globalSearch(false)->breadcrumbs(false));

        expect($shell)->toMatchArray(['globalSearch' => false, 'searchUrl' => null, 'breadcrumbs' => false]);
    });

    it('shows a favicon, and only a safe one', function () {
        expect(Panel::make()->favicon('/icon.png')->getFavicon())->toBe('/icon.png')
            ->and(Panel::make()->favicon('javascript:alert(1)')->getFavicon())->toBeNull();
    });
});

it('leaves out a resource that asks not to be in the navigation', function () {
    Gate::policy(Task::class, TaskPolicy::class);
    $labels = fn (): array => collect(app(Panels::class)->get('admin')->getNavigation())->flatMap(fn (array $group): array => array_column($group['items'], 'label'))->all();

    expect($labels())->toContain('Tasks');

    TaskResource::$showsInNavigation = false;

    try {
        expect($labels())->not->toContain('Tasks');
    } finally {
        TaskResource::$showsInNavigation = true;
    }
});

it('sends a group\'s icon and folding, nests child items, and colors a badge', function () {
    $panel = panelWithPages()->navigationGroups([NavigationGroup::make('Help')->icon('life-buoy')->collapsed()])->navigationItems([
        NavigationItem::make('Docs')->url('https://example.com/docs')->group('Help')->badge(3)->badgeColor('danger')->badgeTooltip('New pages'),
        NavigationItem::make('API')->url('https://example.com/api')->group('Help')->parentItem('Docs'),
        NavigationItem::make('Orphan')->url('https://example.com/x')->group('Help')->parentItem('Nobody'),
    ]);
    defineNavigationRoutes($panel);

    $help = collect($panel->getNavigation())->firstWhere('label', 'Help');

    expect($help)->toMatchArray(['icon' => 'life-buoy', 'collapsible' => true, 'collapsed' => true])
        ->and(array_column($help['items'], 'label'))->toBe(['Docs', 'Orphan'])
        ->and($help['items'][0])->toMatchArray(['badge' => '3', 'badgeColor' => 'danger', 'badgeTooltip' => 'New pages'])
        ->and(array_column($help['items'][0]['children'], 'label'))->toBe(['API']);
});
