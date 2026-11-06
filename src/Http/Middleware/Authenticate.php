<?php

namespace Xelqun\Panels\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Xelqun\Panels\Contracts\PanelUser;
use Xelqun\Panels\Panels;

/**
 * Guards every page of a panel. It sends guests to this panel's own login, not
 * to wherever the app sends them.
 */
final class Authenticate
{
    public function __construct(private readonly Panels $panels) {}

    public function handle(Request $request, Closure $next): Response
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

        return $next($request);
    }
}
