<?php

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Xelqun\Panels\Contracts\Plugin;
use Xelqun\Panels\Exceptions\PanelDefinitionException;
use Xelqun\Panels\Navigation\NavigationItem;
use Xelqun\Panels\Navigation\SidebarCard;
use Xelqun\Panels\Pages\Page;
use Xelqun\Panels\Panel;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Support\Colors\Color;
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
        expect(Panel::make()->colors(['primary' => $color])->getColorVariables()['--xq-panel-primary'])->toBe($color);
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
        expect(Panel::make()->getColorVariables())->toMatchArray([
            '--xq-panel-danger' => Color::Red->value,
            '--xq-panel-gray' => Color::Zinc->value,
            '--xq-panel-info' => Color::Blue->value,
            '--xq-panel-success' => Color::Green->value,
            '--xq-panel-warning' => Color::Amber->value,
        ]);
    });

    it('changes only the colors it names, from a Color or a hex value', function () {
        expect(Panel::make()->colors(['primary' => Color::Amber, 'danger' => '#ff0000'])->getColorVariables())->toMatchArray([
            '--xq-panel-primary' => '#d97706',
            '--xq-panel-primary-foreground' => '#000000',
            '--xq-panel-danger' => '#ff0000',
            '--xq-panel-gray' => '#52525b',
        ]);
    });

    it('chooses text that reads on the color', function (string $color, string $text) {
        expect(Color::foregroundOn($color))->toBe($text);
    })->with([
        'dark blue' => ['#0071e3', '#ffffff'],
        'short form' => ['#fff', '#000000'],
        'black' => ['#000000', '#ffffff'],
        'with alpha' => ['#ffff00ff', '#000000'],
        'yellow' => [Color::Yellow->value, '#000000'],
    ]);

    it('meets WCAG AA for text on every color of the palette, and on any other', function () {
        $colors = [...array_column(Color::cases(), 'value'), '#777777', '#7f7f7f', '#808080', '#868686'];

        foreach ($colors as $color) {
            expect(Color::contrast($color, Color::foregroundOn($color)))->toBeGreaterThanOrEqual(4.5);
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

        expect($panel->getColorVariables()['--xq-panel-primary'])->toBe('#e11d48');

        $color = 'red; background: url(evil)';

        expect(fn () => $panel->getColorVariables())->toThrow(PanelDefinitionException::class);
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
