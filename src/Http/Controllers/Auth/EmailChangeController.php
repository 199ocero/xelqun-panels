<?php

namespace Xelqun\Panels\Http\Controllers\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Xelqun\Notifications\Notification;

/**
 * Opens the link sent to a new address and makes the change. The link is
 * signed and short-lived, and only works for the user it was made for.
 */
final class EmailChangeController extends AuthController
{
    public function verify(Request $request): RedirectResponse
    {
        abort_unless($this->panel()->hasEmailChangeVerification(), 404);

        $user = $request->user();
        $email = $request->query('email');

        abort_unless($user instanceof Model && (string) $user->getKey() === (string) $request->query('id') && is_string($email), 403);

        // Someone may have taken the address since the link was sent.
        $isTaken = Validator::make(['email' => $email], ['email' => ['required', 'email', 'unique:'.$user->getTable().',email,'.$user->getKey().','.$user->getKeyName()]])->fails();

        if ($isTaken) {
            Notification::make()->title(__('xelqun-panels::auth.email_change.taken'))->danger()->send();
        } else {
            $user->forceFill(['email' => $email, 'email_verified_at' => now()])->save();
            Notification::make()->title(__('xelqun-panels::auth.email_change.done'))->success()->send();
        }

        return redirect()->route($this->panel()->routeName('auth.profile'));
    }
}
