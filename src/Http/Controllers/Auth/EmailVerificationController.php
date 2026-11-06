<?php

namespace Xelqun\Panels\Http\Controllers\Auth;

use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Xelqun\Notifications\Notification;
use Xelqun\Panels\Auth\ResendVerificationForm;

final class EmailVerificationController extends AuthController
{
    public function prompt(): Response|RedirectResponse
    {
        $panel = $this->panel();

        if ($panel->hasVerifiedEmail(auth()->user())) {
            return redirect()->to($panel->getUrl());
        }

        return $this->page(
            __('xelqun-panels::auth.verify.heading'),
            __('xelqun-panels::auth.verify.description', ['email' => (string) data_get(auth()->user(), 'email')]),
            ResendVerificationForm::make(),
            route($panel->routeName('auth.verification.send')),
            __('xelqun-panels::auth.verify.submit'),
            [$this->link(__('xelqun-panels::auth.sign_out'), $panel->getLogoutUrl(), 'post')],
        );
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();

            Notification::make()->title(__('xelqun-panels::auth.verify.sent'))->success()->send();
        }

        return redirect()->route($this->panel()->routeName('auth.verification.notice'));
    }

    /**
     * The signature proves the link is ours. The checks here prove it's for the
     * person holding it, so a link from another account can't verify this one.
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user instanceof MustVerifyEmail
            && hash_equals((string) $user->getAuthIdentifier(), $id)
            && hash_equals(sha1($user->getEmailForVerification()), $hash),
            403,
        );

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->to($this->panel()->getUrl());
    }
}
