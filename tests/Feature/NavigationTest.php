<?php

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Xelqun\Panels\Exceptions\PanelDefinitionException;
use Xelqun\Panels\Navigation\NavigationItem;
use Xelqun\Panels\Navigation\SidebarCard;
use Xelqun\Panels\Pages\Page;
use Xelqun\Panels\Panel;
use Xelqun\Panels\Panels;

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
    $this->get('/admin/reports')->assertInertia(fn (Assert $page) => $page
        ->where('xelqunPanel.navigation.0.items.0.active', false)
        ->where('xelqunPanel.navigation.0.items.2.label', 'Reports')
        ->where('xelqunPanel.navigation.0.items.2.active', true));
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
            ->where('xelqunPanel.theme', ['primary' => '#ff0088', 'font' => 'Inter']));
    });

    it('refuses a color that could break out of a style attribute', function (array $colors) {
        Panel::make()->colors($colors);
    })->throws(PanelDefinitionException::class)->with([
        'css' => [['primary' => 'red; background: url(evil)']],
        'quote' => [['primary' => '#000"><script>']],
        'other key' => [['primary' => '#000000', 'danger' => '#ff0000']],
        'too short for a color' => [['primary' => '#0071e']],
        'nothing' => [[]],
    ]);

    it('takes every length of hex color', function (string $color) {
        expect(Panel::make()->colors(['primary' => $color])->getPrimaryColor())->toBe($color);
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
