<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Auth\User as PlainUser;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Security\FormToken;
use Xelqun\Forms\Security\FormTokenEncrypter;
use Xelqun\Panels\Auth\LoginForm;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Tests\Fixtures\Pages\Home;
use Xelqun\Panels\Tests\Fixtures\User;

class PinLoginForm extends LoginForm
{
    public function schema(): array
    {
        return [...parent::schema(), TextInput::make('pin')];
    }
}

beforeEach(function () {
    RateLimiter::clear('xelqun-panels:login:admin:ada@example.com|127.0.0.1');
    Home::$allowed = true;
    $this->user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => Hash::make('secret')]);
});

function login(string $password = 'secret', array $extra = []): TestResponse
{
    return test()->from('/admin/login')->post('/admin/login', ['data' => ['email' => 'ada@example.com', 'password' => $password, ...$extra]]);
}

describe('the login page', function () {
    it('renders a real form', function () {
        $this->get('/admin/login')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('xelqun/PanelAuth')
            ->where('page.form.id', 'login-form')
            ->has('page.form.components', 3)
            ->where('page.action', route('xelqun-panels.admin.auth.login.store')));
    });

    it('shows a guest nothing from inside the panel', function () {
        app(Panels::class)->get('admin')->sidebarFooter(fn () => throw new RuntimeException('Should not run for a guest.'));

        $this->get('/admin/login')->assertInertia(fn (Assert $page) => $page
            ->where('xelqunPanel.navigation', [])
            ->where('xelqunPanel.sidebarFooter', null)
            ->where('xelqunPanel.user', null)
            ->where('xelqunPanel.logoutUrl', null));
    });

    it('sends someone already in to the panel', function () {
        $this->actingAs($this->user, 'admin')->get('/admin/login')->assertRedirect('/admin');
    });

    it('lets someone the panel refuses switch accounts', function () {
        User::$allowed = false;

        $this->actingAs($this->user, 'admin')->get('/admin/login')->assertOk();
    });

    it('takes a form of the app\'s own', function () {
        app(Panels::class)->get('admin')->login(PinLoginForm::class);

        $this->get('/admin/login')->assertInertia(fn (Assert $page) => $page
            ->where('page.form.id', 'pin-login-form')
            ->has('page.form.components', 4));
    });

    it('does not exist on a panel without login()', function () {
        $this->get('/staff/login')->assertNotFound();
    });
});

describe('signing in', function () {
    it('lets the right password in, on the panel\'s guard', function () {
        login()->assertRedirect('/admin');

        expect(auth('admin')->id())->toBe($this->user->id)
            ->and(auth('web')->check())->toBeFalse();
    });

    it('starts a new session, so one set before login is worthless', function () {
        $before = session()->getId();

        login();

        expect(session()->getId())->not->toBe($before);
    });

    it('goes back to the page that asked for it', function () {
        $this->get('/admin/reports')->assertRedirect('/admin/login');

        login()->assertRedirect('/admin/reports');
    });

    it('honours remember me', function (bool $remember) {
        login(extra: ['remember' => $remember]);

        expect((bool) $this->user->fresh()->remember_token)->toBe($remember);
    })->with([true, false]);

    it('refuses a wrong password, on the field the form shows', function () {
        login('nope')->assertRedirect('/admin/login')->assertSessionHasErrors('data.email');

        expect(auth('admin')->check())->toBeFalse();
    });

    it('gives the same answer for an unknown email', function () {
        $this->from('/admin/login')->post('/admin/login', ['data' => ['email' => 'no@example.com', 'password' => 'x']])
            ->assertSessionHasErrors(['data.email' => __('xelqun-panels::auth.failed')]);
    });

    it('checks the form\'s own rules first', function () {
        $this->from('/admin/login')->post('/admin/login', ['data' => ['email' => 'not-an-email']])
            ->assertSessionHasErrors(['data.email', 'data.password']);
    });

    it('never gives a session to someone the panel refuses, and doesn\'t say why', function () {
        User::$allowed = false;

        login()->assertSessionHasErrors(['data.email' => __('xelqun-panels::auth.failed')]);

        expect(auth('admin')->check())->toBeFalse();
    });

    it('never lets a user model without the contract in', function () {
        $user = new class extends PlainUser
        {
            protected $table = 'users';
        };

        expect(app(Panels::class)->get('admin')->allows($user))->toBeFalse();
    });

    it('keeps the password out of the session', function () {
        login('wrong-password');

        expect(serialize(session()->all()))->not->toContain('wrong-password');
    });
});

describe('the rate limit', function () {
    it('stops the sixth try, even with the right password', function () {
        Event::fake([Lockout::class]);

        foreach (range(1, 5) as $attempt) {
            login('nope');
        }

        login()->assertSessionHasErrors('data.email');

        expect(auth('admin')->check())->toBeFalse();
        Event::assertDispatched(Lockout::class);
    });

    it('ignores the case of the email, so it can\'t be dodged', function () {
        foreach (range(1, 5) as $attempt) {
            $this->from('/admin/login')->post('/admin/login', ['data' => ['email' => 'ADA@example.com', 'password' => 'nope']]);
        }

        login();

        expect(auth('admin')->check())->toBeFalse();
    });

    it('starts again after a success', function () {
        foreach (range(1, 4) as $attempt) {
            login('nope');
        }

        login();
        auth('admin')->logout();

        foreach (range(1, 4) as $attempt) {
            login('nope');
        }

        login()->assertRedirect('/admin');
    });

    it('counts each panel on its own', function () {
        expect(RateLimiter::attempts('xelqun-panels:login:staff:ada@example.com|127.0.0.1'))->toBe(0);

        login('nope');

        expect(RateLimiter::attempts('xelqun-panels:login:admin:ada@example.com|127.0.0.1'))->toBe(1)
            ->and(RateLimiter::attempts('xelqun-panels:login:staff:ada@example.com|127.0.0.1'))->toBe(0);
    });
});

describe('the pages of a panel', function () {
    it('send a guest to this panel\'s login', function () {
        $this->get('/admin')->assertRedirect('/admin/login');
    });

    it('refuse a guest on a panel with no login', function () {
        $this->get('/staff')->assertForbidden();
    });

    it('refuse someone the panel refuses', function () {
        User::$allowed = false;

        $this->actingAs($this->user, 'admin')->get('/admin')->assertForbidden();
    });

    it('ignore a user who is only signed in on another guard', function () {
        $this->actingAs($this->user, 'web')->get('/admin')->assertRedirect('/admin/login');
    });

    it('say what is wrong with the user model, only in debug', function (bool $debug, string $text) {
        config(['app.debug' => $debug]);
        $user = (new PlainUser)->forceFill(['id' => 9, 'name' => 'Plain']);

        $this->actingAs($user, 'admin')->withoutExceptionHandling();

        expect(fn () => $this->get('/admin'))->toThrow(function (HttpException $exception) use ($text) {
            expect($exception->getStatusCode())->toBe(403)
                ->and($exception->getMessage())->toBe($text);
        });
    })->with([
        'debug' => [true, '['.PlainUser::class.'] must implement Xelqun\Panels\Contracts\PanelUser before it can enter a panel.'],
        'production' => [false, ''],
    ]);
});

describe('signing out', function () {
    it('ends the session and clears the history', function () {
        $this->actingAs($this->user, 'admin');
        session(['secret' => 'value']);
        $token = session()->token();

        $this->post('/admin/logout')->assertRedirect('/admin/login');

        expect(auth('admin')->check())->toBeFalse()
            ->and(session('secret'))->toBeNull()
            ->and(session()->token())->not->toBe($token)
            ->and(session()->get('inertia.clear_history'))->toBeTrue();
    });

    it('goes home on a panel with no login', function () {
        $this->post('/staff/logout')->assertRedirect('/');
    });
});

describe('after the page has loaded', function () {
    function headerActionsToken(): string
    {
        return test()->get('/admin')->viewData('page')['props']['page']['headerActions']['token'];
    }

    function runHeaderActionAsBrowser(string $token): TestResponse
    {
        return test()->postJson(route('xelqun.forms.action'), ['token' => $token, 'action' => 'data#publish', 'phase' => 'run', 'state' => ['data' => []], 'deps' => (object) []]);
    }

    beforeEach(function () {
        Home::$calls = [];
        $this->actingAs($this->user, 'admin');
    });

    it('refuses the endpoints once the user loses the panel', function () {
        $token = headerActionsToken();
        User::$allowed = false;

        runHeaderActionAsBrowser($token)->assertForbidden();

        expect(Home::$calls)->toBe([]);
    });

    it('asks a signed out user to reload, not a broken page', function () {
        $token = headerActionsToken();
        auth('admin')->logout();

        runHeaderActionAsBrowser($token)->assertStatus(419);
    });

    it('still lets a guest\'s form use the endpoints, for a login form with live fields', function () {
        auth('admin')->logout();
        auth()->shouldUse('web');
        $tokens = app(FormTokenEncrypter::class);
        $this->get('/admin/login');
        $token = $tokens->encrypt(new FormToken(LoginForm::class, null, null));

        expect($tokens->decrypt($token)->scope)->toBe('admin')
            ->and($tokens->resolveForm($token))->toBeInstanceOf(LoginForm::class);
    });
});

describe('encrypted history', function () {
    it('is on over HTTPS', function () {
        $this->actingAs($this->user, 'admin');

        $page = $this->get('https://localhost/admin')->viewData('page');

        expect($page['encryptHistory'])->toBeTrue();
    });

    it('is off over plain HTTP, where the browser can\'t do it', function () {
        $this->actingAs($this->user, 'admin');

        expect($this->get('/admin')->viewData('page'))->not->toHaveKey('encryptHistory');
    });
});
