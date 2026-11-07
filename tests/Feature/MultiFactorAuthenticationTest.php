<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Xelqun\Panels\Auth\AppAuthentication;
use Xelqun\Panels\Auth\Notifications\EmailCode;
use Xelqun\Panels\Auth\ProfileActions;
use Xelqun\Panels\Auth\Totp;
use Xelqun\Panels\Exceptions\PanelDefinitionException;
use Xelqun\Panels\Panel;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Tests\Fixtures\User;

const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

function mfaUser(): User
{
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => Hash::make('secret')]);
    $user->saveAppAuthentication(RFC_SECRET, [AppAuthentication::hashRecoveryCode('abcde-12345')]);

    return $user;
}

function passPassword(): TestResponse
{
    return test()->post('/admin/login', ['data' => ['email' => 'ada@example.com', 'password' => 'secret']]);
}

function currentCode(): string
{
    return Totp::codeAt(RFC_SECRET, (int) floor(now()->getTimestamp() / 30));
}

function answerChallenge(string $code): TestResponse
{
    return test()->post('/admin/two-factor', ['data' => ['code' => $code]]);
}

describe('the algorithm', function () {
    // RFC 6238 appendix B, SHA-1. Its codes have 8 digits, and ours are the last 6.
    it('matches the RFC test vectors', function (int $time, string $code) {
        expect(Totp::codeAt(RFC_SECRET, (int) floor($time / 30)))->toBe($code);
    })->with([
        [59, '287082'],
        [1111111109, '081804'],
        [1111111111, '050471'],
        [1234567890, '005924'],
        [2000000000, '279037'],
        [20000000000, '353130'],
    ]);

    it('takes a code a step early or late, and no further', function () {
        $this->travelTo(now()->setTimestamp(1_000_000_020));
        $step = (int) floor(now()->getTimestamp() / 30);

        expect(Totp::matchingStep(RFC_SECRET, Totp::codeAt(RFC_SECRET, $step - 1)))->toBe($step - 1)
            ->and(Totp::matchingStep(RFC_SECRET, Totp::codeAt(RFC_SECRET, $step + 1)))->toBe($step + 1)
            ->and(Totp::matchingStep(RFC_SECRET, Totp::codeAt(RFC_SECRET, $step - 2)))->toBeNull()
            ->and(Totp::matchingStep(RFC_SECRET, Totp::codeAt(RFC_SECRET, $step + 2)))->toBeNull();
    });

    it('makes a secret an authenticator app reads, and a code from it', function () {
        $secret = Totp::generateSecret();

        expect($secret)->toMatch('/^[A-Z2-7]{32}$/')
            ->and(Totp::codeAt($secret, 1))->toMatch('/^\d{6}$/')
            ->and(Totp::generateSecret())->not->toBe($secret);
    });

    it('writes the setup link apps expect', function () {
        expect(Totp::uri('ABC', 'My App', 'ada@example.com'))
            ->toBe('otpauth://totp/My%20App:ada%40example.com?secret=ABC&issuer=My%20App');
    });
});

describe('signing in', function () {
    beforeEach(function () {
        RateLimiter::clear('x');
        $this->user = mfaUser();
    });

    it('does not sign in on the password alone', function () {
        passPassword()->assertRedirect('/admin/two-factor');

        $this->assertGuest('admin');
        $this->get('/admin')->assertRedirect('/admin/login');
    });

    it('leaves a user with no authenticator as it was', function () {
        $this->user->saveAppAuthentication(null);

        passPassword()->assertRedirect('/admin');

        $this->assertAuthenticatedAs($this->user->fresh(), 'admin');
    });

    it('signs in with a code, and a new session', function () {
        passPassword();
        $id = session()->getId();

        answerChallenge(currentCode())->assertRedirect('/admin');

        $this->assertAuthenticated('admin');
        expect(session()->getId())->not->toBe($id)
            ->and(session()->has('xelqun-panels.challenge.admin'))->toBeFalse();
    });

    it('refuses a wrong code, with the page intact', function () {
        passPassword();

        answerChallenge('000000')->assertSessionHasErrors('data.code');

        $this->assertGuest('admin');
    });

    it('takes a code only once', function () {
        passPassword();
        $code = currentCode();
        answerChallenge($code);
        $this->post('/admin/logout');

        passPassword();
        answerChallenge($code)->assertSessionHasErrors('data.code');

        $this->assertGuest('admin');
    });

    it('takes a recovery code only once', function () {
        passPassword();
        answerChallenge('ABCDE-12345')->assertRedirect('/admin');
        $this->assertAuthenticated('admin');
        $this->post('/admin/logout');

        passPassword();
        answerChallenge('abcde-12345')->assertSessionHasErrors('data.code');

        $this->assertGuest('admin');
    });

    it('stops guessing after 5 tries', function () {
        passPassword();

        foreach (range(1, 5) as $attempt) {
            answerChallenge('000000');
        }

        answerChallenge(currentCode())->assertSessionHasErrors('data.code');

        $this->assertGuest('admin');
    });

    it('gives up on a challenge left too long', function () {
        passPassword();

        $this->travel(6)->minutes();

        answerChallenge(currentCode())->assertRedirect('/admin/login');
        $this->assertGuest('admin');
    });

    it('needs the password step first', function () {
        answerChallenge(currentCode())->assertRedirect('/admin/login');
        $this->get('/admin/two-factor')->assertRedirect('/admin/login');

        $this->assertGuest('admin');
    });

    it('refuses someone who lost access between the steps', function () {
        passPassword();
        User::$allowed = false;

        answerChallenge(currentCode())->assertSessionHasErrors('data.code');

        $this->assertGuest('admin');
    });

    it('keeps the secret and codes out of any array', function () {
        expect($this->user->fresh()->toArray())->not->toHaveKeys(['app_authentication_secret', 'app_authentication_recovery_codes'])
            ->and(DB::table('users')->value('app_authentication_secret'))->not->toBe(RFC_SECRET)
            ->and(DB::table('users')->value('app_authentication_recovery_codes'))->not->toContain('abcde-12345');
    });
});

describe('the profile page', function () {
    beforeEach(function () {
        $this->user = $this->signIn();
        app(Panels::class)->enter(app(Panels::class)->get('admin'));
    });

    /**
     * @param  array<string, mixed>  $input
     */
    function runProfileAction(User $user, string $action, array $input): TestResponse
    {
        return test()->postJson(route('xelqun.forms.action'), [
            'token' => ProfileActions::make($user)->toSchema()['token'],
            'action' => "data#{$action}",
            'phase' => 'run',
            'state' => ['data' => []],
            'data' => $input,
            'deps' => (object) [],
        ]);
    }

    it('offers setup, and turns it on only for a code from the shown key', function () {
        $this->get('/admin/profile')->assertInertia(fn (Assert $page) => $page->where('page.headerActions.components.0.key', fn ($key) => str_contains((string) $key, 'enable')));

        runProfileAction($this->user, 'enable', ['code' => '000000'])->assertUnprocessable();
        expect($this->user->fresh()->getAppAuthenticationSecret())->toBeNull();

        $secret = session('xelqun-panels.pending-secret');

        runProfileAction($this->user, 'enable', ['code' => Totp::codeAt($secret, (int) floor(now()->getTimestamp() / 30))])->assertOk();

        expect($this->user->fresh()->getAppAuthenticationSecret())->toBe($secret)
            ->and(session('xelqun-panels.pending-secret'))->toBeNull();
    });

    it('shows recovery codes once, and keeps only their hashes', function () {
        $this->withSession([ProfileActions::RECOVERY_CODES => ['one-code'], '_flash.old' => [ProfileActions::RECOVERY_CODES]]);

        $this->get('/admin/profile')->assertInertia(fn (Assert $page) => $page->where('page.notice.lines', ['one-code']));
        $this->get('/admin/profile')->assertInertia(fn (Assert $page) => $page->where('page.notice', null));
    });

    it('needs the password to turn it off, or to make new codes', function (string $action) {
        $this->user->saveAppAuthentication(RFC_SECRET, ['old']);

        runProfileAction($this->user, $action, ['current_password' => 'wrong'])->assertUnprocessable();
        expect($this->user->fresh()->getAppAuthenticationSecret())->toBe(RFC_SECRET);

        runProfileAction($this->user, $action, ['current_password' => 'secret'])->assertOk();
        expect($this->user->fresh()->getAttribute('app_authentication_recovery_codes'))->not->toBe(['old']);
    })->with(['disable', 'regenerate']);

    it('turns email codes on and off, with the password', function () {
        runProfileAction($this->user, 'email', ['current_password' => 'wrong'])->assertUnprocessable();
        expect($this->user->fresh()->hasEmailAuthentication())->toBeFalse();

        runProfileAction($this->user, 'email', ['current_password' => 'secret'])->assertOk();
        expect($this->user->fresh()->hasEmailAuthentication())->toBeTrue();
    });

    it('can\'t act on anyone else', function () {
        $other = User::create(['name' => 'Bea', 'email' => 'bea@example.com', 'password' => Hash::make('secret')]);
        $other->saveAppAuthentication(RFC_SECRET, []);

        runProfileAction($other, 'disable', ['current_password' => 'secret'])->assertNotFound();

        expect($other->fresh()->getAppAuthenticationSecret())->toBe(RFC_SECRET);
    });
});

describe('email codes', function () {
    beforeEach(function () {
        Notification::fake();
        $this->user = User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => Hash::make('secret'), 'has_email_authentication' => true]);
    });

    function sentCode(User $user): string
    {
        $code = null;

        Notification::assertSentTo($user, EmailCode::class, function (EmailCode $notification) use (&$code, $user): bool {
            preg_match('/\d{6}/', implode(' ', $notification->toMail($user)->introLines), $matches);
            $code = $matches[0];

            return true;
        });

        return $code;
    }

    it('sends a code, and doesn\'t sign in until it comes back', function () {
        passPassword()->assertRedirect('/admin/two-factor');

        $this->assertGuest('admin');

        answerChallenge(sentCode($this->user))->assertRedirect('/admin');

        $this->assertAuthenticatedAs($this->user->fresh(), 'admin');
    });

    it('refuses a wrong code', function () {
        passPassword();

        answerChallenge('000000')->assertSessionHasErrors('data.code');

        $this->assertGuest('admin');
    });

    it('keeps only a hash of the code in the session', function () {
        passPassword();

        expect(json_encode(session('xelqun-panels.challenge.admin')))->not->toContain(sentCode($this->user));
    });

    it('takes a code only once', function () {
        passPassword();
        $code = sentCode($this->user);
        answerChallenge($code);
        $this->post('/admin/logout');

        answerChallenge($code)->assertRedirect('/admin/login');

        $this->assertGuest('admin');
    });

    it('stops working with the challenge', function () {
        passPassword();
        $code = sentCode($this->user);

        $this->travel(6)->minutes();

        answerChallenge($code)->assertRedirect('/admin/login');
        $this->assertGuest('admin');
    });

    it('leaves a user who hasn\'t turned it on alone', function () {
        $this->user->saveEmailAuthentication(false);

        passPassword()->assertRedirect('/admin');

        Notification::assertNothingSent();
    });
});

describe('requiring it', function () {
    beforeEach(function () {
        app(Panels::class)->get('admin')->multiFactorAuthentication(isRequired: true);
        $this->user = $this->signIn();
    });

    afterEach(function () {
        app(Panels::class)->get('admin')->multiFactorAuthentication();
    });

    it('holds a user at the profile page until they set it up', function () {
        $this->get('/admin')->assertRedirect('/admin/profile');
        $this->get('/admin/profile')->assertOk();

        $this->user->saveAppAuthentication(RFC_SECRET, []);

        $this->get('/admin')->assertOk();
    });

    it('refuses json outright', function () {
        $this->postJson('/admin/_search', ['q' => 'x'])->assertForbidden();
    });
});

it('needs a profile page to require it', function () {
    app(Panels::class)->register(Panel::make()->id('strict')->path('strict')->multiFactorAuthentication(isRequired: true));
})->throws(PanelDefinitionException::class);

it('ships a migration with the columns the trait reads', function () {
    expect(Schema::hasColumns('users', ['app_authentication_secret', 'app_authentication_recovery_codes']))->toBeTrue()
        ->and(file_get_contents(dirname(__DIR__, 2).'/database/migrations/add_app_authentication_to_users_table.php.stub'))
        ->toContain('app_authentication_secret')->toContain('app_authentication_recovery_codes');
});
