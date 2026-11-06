<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Xelqun\Panels\Auth\EditProfileForm;
use Xelqun\Panels\Auth\Notifications\ResetPassword;
use Xelqun\Panels\Contracts\PanelUser;
use Xelqun\Panels\Panel;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Tests\Fixtures\User;

function makeUser(array $attributes = []): User
{
    return User::create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => Hash::make('old-password'), ...$attributes]);
}

describe('password reset', function () {
    it('emails a link back to this panel', function () {
        Notification::fake();
        $user = makeUser();

        $this->post('/admin/forgot-password', ['data' => ['email' => 'ada@example.com']])->assertRedirect('/admin/login');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            return str_contains($notification->toMail($user)->actionUrl, '/admin/reset-password/');
        });
    });

    it('answers the same for an address with no account', function () {
        Notification::fake();
        makeUser();

        $known = $this->post('/admin/forgot-password', ['data' => ['email' => 'ada@example.com']]);
        $unknown = $this->post('/admin/forgot-password', ['data' => ['email' => 'nobody@example.com']]);

        expect($unknown->getStatusCode())->toBe($known->getStatusCode())
            ->and($unknown->headers->get('Location'))->toBe($known->headers->get('Location'));
        $unknown->assertSessionHasNoErrors()->assertInertiaFlash('xelqunNotifications.0.title', __('xelqun-panels::auth.forgot.sent'));
    });

    it('fills in the address from the link', function () {
        $this->get('/admin/reset-password/token?email=ada@example.com')->assertInertia(fn (Assert $page) => $page
            ->component('xelqun/PanelAuth')
            ->where('page.form.state.data.email', 'ada@example.com')
            ->where('page.action', route('xelqun-panels.admin.auth.password.update', ['token' => 'token'])));
    });

    it('changes the password with a good token and ends remember me', function () {
        Event::fake([PasswordReset::class]);
        $user = makeUser(['remember_token' => 'old']);
        $token = Password::broker()->createToken($user);

        $this->post("/admin/reset-password/{$token}", ['data' => [
            'email' => 'ada@example.com', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ]])->assertRedirect('/admin/login');

        expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue()
            ->and($user->fresh()->remember_token)->not->toBe('old');
        Event::assertDispatched(PasswordReset::class);
    });

    it('refuses a bad token, on the field the form shows', function () {
        $user = makeUser();

        $this->from('/admin/reset-password/bad')->post('/admin/reset-password/bad', ['data' => [
            'email' => 'ada@example.com', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ]])->assertSessionHasErrors('data.email');

        expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
    });

    it('refuses a password that does not match its confirmation', function () {
        $user = makeUser();
        $token = Password::broker()->createToken($user);

        $this->post("/admin/reset-password/{$token}", ['data' => [
            'email' => 'ada@example.com', 'password' => 'new-password-123', 'password_confirmation' => 'other',
        ]])->assertSessionHasErrors('data.password_confirmation');
    });

    it('does not exist unless the panel turns it on', function () {
        $this->get('/staff/forgot-password')->assertNotFound();
    });

    it('slows down a flood of requests', function () {
        Notification::fake();

        foreach (range(1, 6) as $attempt) {
            $this->post('/admin/forgot-password', ['data' => ['email' => "a{$attempt}@example.com"]]);
        }

        $this->post('/admin/forgot-password', ['data' => ['email' => 'a@example.com']])->assertStatus(429);
    });
});

describe('registration', function () {
    function register(array $data = []): TestResponse
    {
        return test()->post('/admin/register', ['data' => [
            'name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'a-good-password', 'password_confirmation' => 'a-good-password', ...$data,
        ]]);
    }

    it('creates the user, with a hashed password, and signs them in', function () {
        Event::fake([Registered::class]);

        register()->assertRedirect('/admin');

        $user = User::query()->firstWhere('email', 'ada@example.com');

        expect(Hash::check('a-good-password', $user->password))->toBeTrue()
            ->and(auth('admin')->id())->toBe($user->id);
        Event::assertDispatched(Registered::class);
    });

    it('sets nothing the form doesn\'t declare', function () {
        register(['email_verified_at' => '2020-01-01', 'remember_token' => 'mine', 'id' => 99]);

        expect(User::query()->firstWhere('email', 'ada@example.com'))
            ->id->not->toBe(99)
            ->email_verified_at->toBeNull()
            ->remember_token->toBeNull();
    });

    it('leaves out a user the panel refuses', function () {
        User::$allowed = false;

        register()->assertRedirect('/admin/login');

        expect(auth('admin')->check())->toBeFalse()
            ->and(User::query()->where('email', 'ada@example.com')->exists())->toBeTrue();
    });

    it('refuses an address already in use', function () {
        makeUser();

        $this->from('/admin/register');
        register()->assertSessionHasErrors('data.email');

        expect(User::query()->count())->toBe(1);
    });

    it('sends someone already in to the panel', function () {
        $this->actingAs(makeUser(), 'admin')->get('/admin/register')->assertRedirect('/admin');
    });

    it('does not exist unless the panel turns it on', function () {
        $this->get('/staff/register')->assertNotFound();
    });
});

describe('email verification', function () {
    function verificationUrl(User $user, ?string $hash = null): string
    {
        return URL::temporarySignedRoute('xelqun-panels.staff.auth.verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => $hash ?? sha1($user->email),
        ]);
    }

    beforeEach(function () {
        $this->user = makeUser();
        $this->actingAs($this->user, 'web');
    });

    it('holds an unverified user at the prompt', function () {
        $this->get('/staff')->assertRedirect('/staff/email/verify');

        $this->get('/staff/email/verify')->assertInertia(fn (Assert $page) => $page
            ->component('xelqun/PanelAuth')
            ->where('page.links.0.method', 'post')
            ->where('xelqunPanel.navigation', []));
    });

    it('lets a verified user through', function () {
        $this->user->markEmailAsVerified();

        $this->get('/staff')->assertOk();
        $this->get('/staff/email/verify')->assertRedirect('/staff');
    });

    it('verifies from a signed link', function () {
        Event::fake([Verified::class]);

        $this->get(verificationUrl($this->user))->assertRedirect(url('staff'));

        expect($this->user->fresh()->hasVerifiedEmail())->toBeTrue();
        Event::assertDispatched(Verified::class);
    });

    it('refuses a link that isn\'t signed, is tampered with, or has expired', function (Closure $url) {
        $this->get($url($this->user))->assertForbidden();

        expect($this->user->fresh()->hasVerifiedEmail())->toBeFalse();
    })->with([
        'not signed' => [fn (User $user): string => "/staff/email/verify/{$user->id}/".sha1($user->email)],
        'tampered' => [fn (User $user): string => verificationUrl($user).'x'],
        'expired' => [fn (User $user): string => URL::temporarySignedRoute('xelqun-panels.staff.auth.verification.verify', now()->subMinute(), ['id' => $user->id, 'hash' => sha1($user->email)])],
    ]);

    it('refuses a user model that can\'t verify, rather than trapping it at the prompt', function () {
        $user = new class extends Authenticatable implements PanelUser
        {
            protected $table = 'users';

            protected $guarded = [];

            public function canAccessPanel(Panel $panel): bool
            {
                return true;
            }
        };
        $user->forceFill(['id' => 9, 'name' => 'Plain', 'email' => 'plain@example.com']);

        $this->actingAs($user, 'web')->get('/staff')->assertForbidden();
    });

    it('refuses a real link of someone else\'s account', function () {
        $other = makeUser(['email' => 'other@example.com']);

        $this->get(verificationUrl($other))->assertForbidden();

        expect($this->user->fresh()->hasVerifiedEmail())->toBeFalse()
            ->and($other->fresh()->hasVerifiedEmail())->toBeFalse();
    });

    it('refuses a link for an address the user no longer has', function () {
        $this->get(verificationUrl($this->user, sha1('old@example.com')))->assertForbidden();
    });

    it('sends a link into this panel', function () {
        Notification::fake();

        $this->post('/staff/email/send')->assertRedirect(route('xelqun-panels.staff.auth.verification.notice'));

        Notification::assertSentTo($this->user, VerifyEmail::class, function (VerifyEmail $notification): bool {
            return str_contains($notification->toMail($this->user)->actionUrl, '/staff/email/verify/');
        });
    });

    it('stops at the endpoints too', function () {
        $token = $this->get('/staff/email/verify')->viewData('page')['props']['page']['form']['token'];
        $panel = app(Panels::class)->get('staff');

        expect($panel->hasVerifiedEmail($this->user))->toBeFalse();

        $this->postJson(route('xelqun.forms.update'), ['token' => $token, 'seq' => 1, 'state' => ['data' => []], 'changed' => [], 'deps' => (object) []])
            ->assertForbidden();
    });
});

describe('the verification link', function () {
    function verificationLink(Model&MustVerifyEmail $user): string
    {
        return (new VerifyEmail)->toMail($user)->actionUrl;
    }

    beforeEach(function () {
        Route::get('verify/{id}/{hash}', fn () => null)->name('verification.verify');
        app('router')->getRoutes()->refreshNameLookups();
    });

    it('stays Laravel\'s own outside a panel', function () {
        expect(verificationLink(makeUser()))->toContain('/verify/');
    });

    it('stays Laravel\'s own in a panel that doesn\'t verify', function () {
        app(Panels::class)->enter(app(Panels::class)->get('admin'));

        expect(verificationLink(makeUser()))->not->toContain('/admin/');
    });

    it('uses the model\'s real key, whatever it is called', function () {
        $user = new class extends User
        {
            protected $primaryKey = 'uuid';

            protected $keyType = 'string';
        };
        $user->forceFill(['uuid' => 'abc-123', 'email' => 'ada@example.com']);

        expect(verificationLink($user))->toContain('/abc-123/');
    });
});

describe('the profile page', function () {
    beforeEach(function () {
        $this->user = makeUser();
        $this->actingAs($this->user, 'admin');
    });

    function saveProfile(array $data = []): TestResponse
    {
        return test()->from('/admin/profile')->put('/admin/profile', ['data' => ['name' => 'Ada', 'email' => 'ada@example.com', ...$data]]);
    }

    it('shows the user\'s details and never their password', function () {
        $this->get('/admin/profile')->assertInertia(fn (Assert $page) => $page
            ->component('xelqun/Panel')
            ->where('page.content.0.type', 'form')
            ->where('page.content.0.schema.state.data.name', 'Ada')
            ->where('page.content.0.schema.state.data.password', null));
    });

    it('needs a user', function () {
        auth('admin')->logout();

        $this->get('/admin/profile')->assertRedirect('/admin/login');
    });

    it('saves new details', function () {
        saveProfile(['name' => 'Grace'])->assertRedirect(route('xelqun-panels.admin.auth.profile'));

        expect($this->user->fresh()->name)->toBe('Grace');
    });

    it('leaves the password alone when the box is empty', function () {
        saveProfile(['password' => '']);

        expect(Hash::check('old-password', $this->user->fresh()->password))->toBeTrue();
    });

    it('changes the password only with the current one', function () {
        saveProfile(['password' => 'new-password-123', 'password_confirmation' => 'new-password-123', 'current_password' => 'old-password'])
            ->assertSessionHasNoErrors();

        expect(Hash::check('new-password-123', $this->user->fresh()->password))->toBeTrue()
            ->and($this->user->fresh()->remember_token)->not->toBeNull();
    });

    it('refuses a new password with a missing or wrong current one', function (array $current) {
        saveProfile(['password' => 'new-password-123', 'password_confirmation' => 'new-password-123', ...$current])
            ->assertSessionHasErrors('data.current_password');

        expect(Hash::check('old-password', $this->user->fresh()->password))->toBeTrue();
    })->with([
        'missing' => [[]],
        'wrong' => [['current_password' => 'nope']],
    ]);

    it('clears verification when the address changes, and asks again', function () {
        Notification::fake();
        $this->user->markEmailAsVerified();

        saveProfile(['email' => 'new@example.com']);

        expect($this->user->fresh()->hasVerifiedEmail())->toBeFalse();
        Notification::assertSentTo($this->user->fresh(), VerifyEmail::class);
    });

    it('keeps verification when the address stays', function () {
        $this->user->markEmailAsVerified();

        saveProfile(['name' => 'Grace']);

        expect($this->user->fresh()->hasVerifiedEmail())->toBeTrue();
    });

    it('refuses an address another user has', function () {
        makeUser(['email' => 'other@example.com']);

        saveProfile(['email' => 'other@example.com'])->assertSessionHasErrors('data.email');
    });

    it('allows keeping your own address', function () {
        saveProfile()->assertSessionHasNoErrors();
    });

    it('can\'t reach another user, even with their key in a token', function () {
        $other = makeUser(['email' => 'other@example.com']);

        expect((new EditProfileForm(null, []))->resolveRecord($other->id)->getKey())->toBe($this->user->id);
    });

    it('ignores fields the form doesn\'t declare', function () {
        saveProfile(['email_verified_at' => '2020-01-01']);

        expect($this->user->fresh()->email_verified_at)->toBeNull();
    });

    it('does not exist unless the panel turns it on', function () {
        $this->actingAs($this->user, 'web');
        $this->user->markEmailAsVerified();

        $this->get('/staff/profile')->assertNotFound();
    });
});
