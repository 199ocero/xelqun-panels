<?php

namespace Xelqun\Panels\Http\Controllers\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Xelqun\Panels\Auth\LoginForm;
use Xelqun\Panels\Contracts\PanelUser;
use Xelqun\Panels\Exceptions\PanelDefinitionException;

final class LoginController extends AuthController
{
    private const int MAX_ATTEMPTS = 5;

    public function show(): Response|RedirectResponse
    {
        $panel = $this->panel();

        // Someone signed in but not allowed here gets the form, so they can switch accounts.
        if ($panel->allows(auth()->user())) {
            return redirect()->to($panel->getUrl());
        }

        return $this->page(
            __('xelqun-panels::auth.login.heading'),
            __('xelqun-panels::auth.login.description'),
            $this->form(),
            route($panel->routeName('auth.login.store')),
            __('xelqun-panels::auth.login.submit'),
            [
                $this->link(__('xelqun-panels::auth.login.forgot'), $panel->getPasswordResetRequestForm() === null ? null : route($panel->routeName('auth.password.request'))),
                $this->link(__('xelqun-panels::auth.login.register'), $panel->getRegistrationForm() === null ? null : route($panel->routeName('auth.register'))),
            ],
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $panel = $this->panel();
        $data = $this->form()->validate($request);
        $rateLimitKey = 'xelqun-panels:login:'.$panel->getId().':'.Str::lower((string) $data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::MAX_ATTEMPTS)) {
            event(new Lockout($request));

            throw ValidationException::withMessages([
                'data.email' => __('xelqun-panels::auth.throttled', ['seconds' => RateLimiter::availableIn($rateLimitKey)]),
            ]);
        }

        $guard = Auth::guard($panel->getAuthGuard());

        if (! $guard instanceof SessionGuard) {
            throw new PanelDefinitionException("The panel [{$panel->getId()}] needs a session guard to sign users in.");
        }

        $this->explainMissingContract($guard);

        // Checked inside the attempt, so someone the panel refuses never gets a session.
        $credentials = ['email' => $data['email'], 'password' => $data['password']];

        // Someone with a second step stops here too, so the right password alone never makes a session.
        // shortcut: Laravel fires its Failed event for them, as it can't tell why the callback said no. Check the credentials by hand if an app audits that event.
        $challenged = null;
        $remember = (bool) ($data['remember'] ?? false);
        $isSignedIn = $guard->attemptWhen($credentials, function ($user) use ($panel, &$challenged): bool {
            if ($panel->allows($user) && $panel->needsChallenge($user)) {
                $challenged = $user;

                return false;
            }

            return $panel->allows($user);
        }, $remember);

        if ($challenged !== null) {
            RateLimiter::clear($rateLimitKey);

            return app(MultiFactorChallengeController::class)->begin($request, $challenged, $remember);
        }

        if (! $isSignedIn) {
            RateLimiter::hit($rateLimitKey);

            throw ValidationException::withMessages(['data.email' => __('xelqun-panels::auth.failed')]);
        }

        RateLimiter::clear($rateLimitKey);
        $request->session()->regenerate();

        return redirect()->intended($panel->getUrl());
    }

    /**
     * Refused users all see the same failure, which is right for everyone but a
     * developer who hasn't added the contract yet. With the right password, that
     * reads like a bug.
     */
    private function explainMissingContract(SessionGuard $guard): void
    {
        $provider = $guard->getProvider();

        if (config('app.debug') && $provider instanceof EloquentUserProvider && ! $provider->createModel() instanceof PanelUser) {
            throw ValidationException::withMessages([
                'data.email' => __('xelqun-panels::auth.not_a_panel_user', ['model' => $provider->getModel()]),
            ]);
        }
    }

    private function form(): LoginForm
    {
        return $this->panel()->getLoginForm()::make();
    }
}
