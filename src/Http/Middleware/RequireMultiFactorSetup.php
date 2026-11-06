<?php

namespace Xelqun\Panels\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Xelqun\Notifications\Notification;
use Xelqun\Panels\Panels;

/**
 * Holds a user at the profile page on a panel that requires two-factor. It comes
 * after IdentifyTenant, because that page's URL can need the tenant.
 */
final class RequireMultiFactorSetup
{
    public function __construct(private readonly Panels $panels) {}

    public function handle(Request $request, Closure $next): Response
    {
        $panel = $this->panels->current();

        if ($panel->mustSetUpMultiFactorAuthentication($request->user()) && ! $request->routeIs($panel->routeName('auth.profile*'))) {
            abort_if($request->wantsJson(), 403);

            Notification::make()->title(__('xelqun-panels::auth.mfa.required'))->warning()->send();

            return redirect()->route($panel->routeName('auth.profile'));
        }

        return $next($request);
    }
}
