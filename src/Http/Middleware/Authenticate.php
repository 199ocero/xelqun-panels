<?php

namespace Xelqun\Panels\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Xelqun\Panels\Contracts\PanelUser;
use Xelqun\Panels\Panels;

/**
 * Guards every page of a panel. It sends guests to this panel's own login, not
 * to wherever the app sends them. The `unverified` option is for the routes
 * someone needs in order to verify.
 */
final class Authenticate
{
    public function __construct(private readonly Panels $panels) {}

    public function handle(Request $request, Closure $next, ?string $option = null): Response
    {
        $panel = $this->panels->current();
        $user = $request->user();

        if ($user === null) {
            $loginUrl = $panel->getLoginUrl();

            abort_if($loginUrl === null, 403);

            return redirect()->guest($loginUrl);
        }

        if (! $panel->allows($user)) {
            $reason = config('app.debug') && ! $user instanceof PanelUser
                ? __('xelqun-panels::auth.not_a_panel_user', ['model' => $user::class])
                : '';

            abort(403, $reason);
        }

        if ($option !== 'unverified' && ! $panel->hasVerifiedEmail($user)) {
            // A model that can't verify would be stuck on the prompt, so it's refused instead.
            abort_unless($user instanceof MustVerifyEmail, 403, config('app.debug') ? __('xelqun-panels::auth.cannot_verify', ['model' => $user::class]) : '');

            return redirect()->route($panel->routeName('auth.verification.notice'));
        }

        return $next($request);
    }
}
