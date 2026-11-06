<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Xelqun\Forms\Security\FormToken;
use Xelqun\Forms\Security\FormTokenEncrypter;
use Xelqun\Notifications\Notification;
use Xelqun\Panels\Exceptions\PanelDefinitionException;
use Xelqun\Panels\Http\Middleware\EnterPanel;
use Xelqun\Panels\Panel;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Tests\Fixtures\Pages\Home;

beforeEach(function () {
    Home::$allowed = true;
    Home::$calls = [];
    $this->user = $this->signIn();
});

function runHeaderAction(string $token): TestResponse
{
    return test()->postJson(route('xelqun.forms.action'), [
        'token' => $token,
        'action' => 'data#publish',
        'phase' => 'run',
        'state' => ['data' => []],
        'deps' => (object) [],
    ]);
}

it('renders a page inside its panel', function () {
    $this->get('/admin')
        ->assertOk()
        ->assertViewIs('xelqun-panels::app')
        ->assertInertia(fn (Assert $page) => $page
            ->component('xelqun/Panel')
            ->where('page.title', 'Home')
            ->where('xelqunPanel.id', 'admin')
            ->where('xelqunPanel.brand.name', 'Admin')
            ->where('xelqunPanel.user', ['name' => 'Ada', 'email' => 'ada@example.com']));
});

it('keeps two panels apart, each on its own guard', function () {
    $this->get('/admin')->assertInertia(fn (Assert $page) => $page->where('xelqunPanel.id', 'admin'));
    expect(auth()->getDefaultDriver())->toBe('admin');

    $this->get('/staff')->assertInertia(fn (Assert $page) => $page->where('xelqunPanel.id', 'staff'));
    expect(auth()->getDefaultDriver())->toBe('web');
});

it('names the app in a panel with no brand of its own', function () {
    config(['app.name' => 'Shop']);

    $this->get('/staff')->assertInertia(fn (Assert $page) => $page->where('xelqunPanel.brand.name', 'Shop'));
});

it('leaves out the parts of a page the user may not see', function () {
    $this->get('/admin/partly-denied')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('page.content', 1)
        ->where('page.content.0.type', 'table')
        ->where('page.content.0.schema.id', 'open-users-table'));
});

it('shares nothing about a panel with a page outside it', function () {
    Route::middleware('web')->get('outside', fn () => Inertia::render('Outside'));

    $this->get('/admin')->assertOk();

    $this->get('/outside')->assertInertia(fn (Assert $page) => $page->where('xelqunPanel', null));
});

it('refuses a page the user may not open', function () {
    Home::$allowed = false;

    $this->get('/admin')->assertForbidden();
});

it('renders a Vue page of the app, with the shell', function () {
    $this->get('/admin/reports')->assertInertia(fn (Assert $page) => $page
        ->component('reports/Index')
        ->where('total', 3)
        ->where('page.title', 'Reports')
        ->where('xelqunPanel.id', 'admin'));
});

it('runs a header action on the panel\'s guard, however the request starts', function () {
    $token = $this->get('/admin')->viewData('page')['props']['page']['headerActions']['token'];
    Auth::shouldUse('web');

    runHeaderAction($token)->assertOk();

    expect(Home::$calls)->toBe(['admin']);
});

it('checks the page again on every click', function () {
    $token = $this->get('/admin')->viewData('page')['props']['page']['headerActions']['token'];
    Home::$allowed = false;

    runHeaderAction($token)->assertForbidden();

    expect(Home::$calls)->toBe([]);
});

it('refuses a token of a panel that no longer exists', function () {
    $tokens = app(FormTokenEncrypter::class);
    $tokens->scopeUsing(fn (): string => 'gone', fn () => null);
    $token = $tokens->encrypt(new FormToken('Form', null, null));
    $this->refreshApplication();

    runHeaderAction($token)->assertForbidden();
});

it('flashes a notification sent from a panel route', function () {
    Route::middleware(['web', EnterPanel::class.':admin'])
        ->name(app(Panels::class)->get('admin')->routeName('save'))
        ->post('admin/save', function () {
            Notification::make()->title('Saved')->success()->send();

            return redirect('/admin');
        });

    $this->post('admin/save')
        ->assertRedirect('/admin')
        ->assertInertiaFlash('xelqunNotifications.0.title', 'Saved');
});

it('refuses a panel that can\'t work', function (Closure $panel, string $message) {
    expect(fn () => app(Panels::class)->register($panel()))->toThrow(PanelDefinitionException::class, $message);
})->with([
    'no id' => [fn (): Panel => Panel::make()->path('x'), 'needs an id'],
    'same id' => [fn (): Panel => Panel::make()->id('admin')->path('x'), 'Two panels'],
    'same path' => [fn (): Panel => Panel::make()->id('other')->path('admin'), 'same path'],
]);

it('refuses an id that can\'t go in a route name', function () {
    Panel::make()->id('Admin Panel');
})->throws(PanelDefinitionException::class);
