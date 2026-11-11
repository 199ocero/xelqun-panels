<?php

namespace Xelqun\Panels\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Xelqun\Panels\Panels;

/**
 * Laravel's check that the password hasn't changed since this session signed in,
 * so a password changed or reset elsewhere signs every other session out. It
 * sends them to this panel's login, where Laravel would look for a `login` route.
 */
final class AuthenticatePanelSession extends AuthenticateSession
{
    protected function redirectTo(Request $request): ?string
    {
        return app(Panels::class)->current()?->getLoginUrl();
    }
}
