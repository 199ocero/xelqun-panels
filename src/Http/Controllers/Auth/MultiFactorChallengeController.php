<?php

namespace Xelqun\Panels\Http\Controllers\Auth;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Xelqun\Notifications\Notification;
use Xelqun\Panels\Auth\AppAuthentication;
use Xelqun\Panels\Auth\MultiFactorChallengeForm;
use Xelqun\Panels\Auth\Notifications\EmailCode;
use Xelqun\Panels\Contracts\HasAppAuthentication;

/**
 * The second step of signing in. Until it passes there's no login, only a note in
 * the session of who got the password right, which expires.
 */
final class MultiFactorChallengeController extends AuthController
{
    private const int MAX_ATTEMPTS = 5;

    /** Long enough that guessing a 6 digit code takes years, not weeks. */
    private const int LOCKOUT_SECONDS = 900;

    private const int EXPIRES_IN_SECONDS = 300;

    public function show(Request $request): Response|RedirectResponse
    {
        $pending = $this->pending($request);

        if ($pending === null) {
            return redirect()->to($this->panel()->getLoginUrl());
        }

        return $this->page(
            __('xelqun-panels::auth.mfa.heading'),
            // Someone who only gets codes by email has no app to open.
            __($pending['emailCode'] === null ? 'xelqun-panels::auth.mfa.description' : 'xelqun-panels::auth.mfa.description_email'),
            MultiFactorChallengeForm::make(),
            route($this->panel()->routeName('auth.challenge.store')),
            __('xelqun-panels::auth.mfa.submit'),
            array_filter([
                $pending['emailCode'] === null ? null : $this->link(__('xelqun-panels::auth.mfa.resend'), route($this->panel()->routeName('auth.challenge.resend')), 'post'),
                $this->link(__('xelqun-panels::auth.mfa.back'), $this->panel()->getLoginUrl()),
            ]),
        );
    }

    /**
     * A new code, for one that didn't arrive or ran out. It replaces the old one
     * and gives the challenge its full time again.
     */
    public function resend(Request $request): RedirectResponse
    {
        $pending = $this->pending($request);

        if ($pending === null) {
            return redirect()->to($this->panel()->getLoginUrl());
        }

        /** @var SessionGuard $guard */
        $guard = Auth::guard($this->panel()->getAuthGuard());
        $user = $guard->getProvider()->retrieveById($pending['id']);

        if ($pending['emailCode'] !== null && $user !== null && $this->panel()->sendsEmailCodeTo($user)) {
            $request->session()->put($this->sessionKey(), [
                ...$pending,
                'expires' => now()->addSeconds(self::EXPIRES_IN_SECONDS)->getTimestamp(),
                'emailCode' => $this->sendEmailCode($user),
            ]);

            Notification::make()->title(__('xelqun-panels::auth.mfa.resent'))->success()->send();
        }

        return redirect()->route($this->panel()->routeName('auth.challenge'));
    }

    public function store(Request $request): RedirectResponse
    {
        $panel = $this->panel();
        $pending = $this->pending($request);

        if ($pending === null) {
            return redirect()->to($panel->getLoginUrl());
        }

        $data = MultiFactorChallengeForm::make()->validate($request);
        // Per user, with no IP in it, or changing IP would give an attacker fresh guesses.
        $rateLimitKey = "xelqun-panels:challenge:{$panel->getId()}:{$pending['id']}";

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'data.code' => __('xelqun-panels::auth.throttled', ['seconds' => RateLimiter::availableIn($rateLimitKey)]),
            ]);
        }

        /** @var SessionGuard $guard */
        $guard = Auth::guard($panel->getAuthGuard());
        $user = $guard->getProvider()->retrieveById($pending['id']);

        if (! $panel->allows($user) || ! $this->passes($user, $pending, (string) $data['code'])) {
            RateLimiter::hit($rateLimitKey, self::LOCKOUT_SECONDS);
            $failures = ($pending['failures'] ?? 0) + 1;

            // Back to the password, so an emailed code can't take more guesses than this, resent or not.
            if ($failures >= self::MAX_ATTEMPTS) {
                $request->session()->forget($this->sessionKey());
                Notification::make()->title(__('xelqun-panels::auth.mfa.too_many'))->danger()->send();

                return redirect()->to($panel->getLoginUrl());
            }

            $request->session()->put($this->sessionKey(), [...$pending, 'failures' => $failures]);

            throw ValidationException::withMessages(['data.code' => __('xelqun-panels::auth.mfa.invalid')]);
        }

        RateLimiter::clear($rateLimitKey);
        $request->session()->forget($this->sessionKey());
        $guard->login($user, $pending['remember']);
        $request->session()->regenerate();
        $panel->markChallengePassed($user);

        return redirect()->intended($panel->getUrl());
    }

    /**
     * Someone already signed in, through another way in, who hasn't passed the
     * second step here. A challenge in progress keeps its code.
     */
    public function resume(Request $request, Authenticatable $user): RedirectResponse
    {
        if ($this->pending($request) !== null) {
            return redirect()->route($this->panel()->routeName('auth.challenge'));
        }

        return $this->begin($request, $user, false);
    }

    /**
     * Note that this user passed the password step. An emailed code is kept here
     * only as a hash.
     */
    public function begin(Request $request, Authenticatable $user, bool $remember): RedirectResponse
    {
        $request->session()->regenerate();
        $request->session()->put($this->sessionKey(), [
            'id' => $user->getAuthIdentifier(),
            'remember' => $remember,
            'expires' => now()->addSeconds(self::EXPIRES_IN_SECONDS)->getTimestamp(),
            'emailCode' => $this->panel()->sendsEmailCodeTo($user) ? $this->sendEmailCode($user) : null,
        ]);

        return redirect()->route($this->panel()->routeName('auth.challenge'));
    }

    /**
     * @return string The code's hash, for the session.
     */
    private function sendEmailCode(Authenticatable $user): string
    {
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        NotificationFacade::send($user, new EmailCode($code));

        return hash('sha256', $code.$user->getAuthIdentifier());
    }

    /**
     * @return array{id: mixed, remember: bool, expires: int, emailCode: string|null, failures?: int}|null
     */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get($this->sessionKey());

        return is_array($pending) && $pending['expires'] > now()->getTimestamp() ? $pending : null;
    }

    /**
     * An emailed code goes when the challenge does, so it works once.
     *
     * @param  array{id: mixed, remember: bool, expires: int, emailCode: string|null, failures?: int}  $pending
     */
    private function passes(?Authenticatable $user, array $pending, string $code): bool
    {
        if ($pending['emailCode'] !== null && hash_equals($pending['emailCode'], hash('sha256', trim($code).$pending['id']))) {
            return true;
        }

        return $user instanceof HasAppAuthentication && AppAuthentication::verify($user, $code);
    }

    private function sessionKey(): string
    {
        return "xelqun-panels.challenge.{$this->panel()->getId()}";
    }
}
