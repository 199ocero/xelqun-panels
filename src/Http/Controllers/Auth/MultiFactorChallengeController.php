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

    private const int EXPIRES_IN_SECONDS = 300;

    public function show(Request $request): Response|RedirectResponse
    {
        if ($this->pending($request) === null) {
            return redirect()->to($this->panel()->getLoginUrl());
        }

        return $this->page(
            __('xelqun-panels::auth.mfa.heading'),
            __('xelqun-panels::auth.mfa.description'),
            MultiFactorChallengeForm::make(),
            route($this->panel()->routeName('auth.challenge.store')),
            __('xelqun-panels::auth.mfa.submit'),
            [$this->link(__('xelqun-panels::auth.mfa.back'), $this->panel()->getLoginUrl())],
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $panel = $this->panel();
        $pending = $this->pending($request);

        if ($pending === null) {
            return redirect()->to($panel->getLoginUrl());
        }

        $data = MultiFactorChallengeForm::make()->validate($request);
        $rateLimitKey = "xelqun-panels:challenge:{$panel->getId()}:{$pending['id']}|{$request->ip()}";

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'data.code' => __('xelqun-panels::auth.throttled', ['seconds' => RateLimiter::availableIn($rateLimitKey)]),
            ]);
        }

        /** @var SessionGuard $guard */
        $guard = Auth::guard($panel->getAuthGuard());
        $user = $guard->getProvider()->retrieveById($pending['id']);

        if (! $panel->allows($user) || ! $this->passes($user, $pending, (string) $data['code'])) {
            RateLimiter::hit($rateLimitKey);

            throw ValidationException::withMessages(['data.code' => __('xelqun-panels::auth.mfa.invalid')]);
        }

        RateLimiter::clear($rateLimitKey);
        $request->session()->forget($this->sessionKey());
        $guard->login($user, $pending['remember']);
        $request->session()->regenerate();

        return redirect()->intended($panel->getUrl());
    }

    /**
     * Note that this user passed the password step. An emailed code is kept here
     * only as a hash.
     */
    public function begin(Request $request, Authenticatable $user, bool $remember): RedirectResponse
    {
        $code = $this->panel()->sendsEmailCodeTo($user) ? str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT) : null;

        $request->session()->regenerate();
        $request->session()->put($this->sessionKey(), [
            'id' => $user->getAuthIdentifier(),
            'remember' => $remember,
            'expires' => now()->addSeconds(self::EXPIRES_IN_SECONDS)->getTimestamp(),
            'emailCode' => $code === null ? null : hash('sha256', $code.$user->getAuthIdentifier()),
        ]);

        if ($code !== null) {
            NotificationFacade::send($user, new EmailCode($code));
        }

        return redirect()->route($this->panel()->routeName('auth.challenge'));
    }

    /**
     * @return array{id: mixed, remember: bool, expires: int, emailCode: string|null}|null
     */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get($this->sessionKey());

        return is_array($pending) && $pending['expires'] > now()->getTimestamp() ? $pending : null;
    }

    /**
     * An emailed code goes when the challenge does, so it works once.
     *
     * @param  array{id: mixed, remember: bool, expires: int, emailCode: string|null}  $pending
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
