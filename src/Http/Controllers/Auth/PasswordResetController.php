<?php

namespace Xelqun\Panels\Http\Controllers\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification as Mailer;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Xelqun\Notifications\Notification;
use Xelqun\Panels\Auth\Notifications\ResetPassword;

final class PasswordResetController extends AuthController
{
    public function showRequest(): Response
    {
        $panel = $this->panel();

        return $this->page(
            __('xelqun-panels::auth.forgot.heading'),
            __('xelqun-panels::auth.forgot.description'),
            $panel->getPasswordResetRequestForm()::make(),
            route($panel->routeName('auth.password.email')),
            __('xelqun-panels::auth.forgot.submit'),
            [$this->link(__('xelqun-panels::auth.back_to_login'), $panel->getLoginUrl())],
        );
    }

    /**
     * The answer is the same whether the address exists or not, or else this
     * would tell anyone who has an account. The broker's own timebox hides the
     * difference in time.
     */
    public function sendLink(Request $request): RedirectResponse
    {
        $panel = $this->panel();
        $data = $panel->getPasswordResetRequestForm()::make()->validate($request);

        Password::broker($panel->getPasswordBroker())->sendResetLink(
            ['email' => $data['email']],
            function (CanResetPassword $user, string $token) use ($panel): void {
                Mailer::send($user, new ResetPassword($token, route($panel->routeName('auth.password.reset'), [
                    'token' => $token,
                    'email' => $user->getEmailForPasswordReset(),
                ])));
            },
        );

        Notification::make()->title(__('xelqun-panels::auth.forgot.sent'))->success()->send();

        return redirect()->to($panel->getLoginUrl() ?? '/');
    }

    public function showReset(Request $request, string $token): Response
    {
        $panel = $this->panel();

        return $this->page(
            __('xelqun-panels::auth.reset.heading'),
            null,
            $panel->getPasswordResetForm()::make(null, ['email' => (string) $request->query('email')]),
            route($panel->routeName('auth.password.update'), ['token' => $token]),
            __('xelqun-panels::auth.reset.submit'),
        );
    }

    public function reset(Request $request, string $token): RedirectResponse
    {
        $panel = $this->panel();
        $data = $panel->getPasswordResetForm()::make()->validate($request);

        $status = Password::broker($panel->getPasswordBroker())->reset(
            ['email' => $data['email'], 'password' => $data['password'], 'token' => $token],
            function (Authenticatable&CanResetPassword&Model $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['data.email' => __('xelqun-panels::auth.reset.invalid')]);
        }

        Notification::make()->title(__('xelqun-panels::auth.reset.done'))->success()->send();

        return redirect()->to($panel->getLoginUrl() ?? '/');
    }
}
