<?php

namespace Xelqun\Panels\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Xelqun\Panels\Panels;

/**
 * Runs after Authenticate on a panel with tenants. A tenant the user isn't in is a
 * 404, the same as one that doesn't exist, so a guess confirms nothing.
 */
final class IdentifyTenant
{
    public function __construct(private readonly Panels $panels) {}

    public function handle(Request $request, Closure $next): Response
    {
        $panel = $this->panels->current();
        $route = $request->route();
        $tenant = $panel->findTenant((string) $route->parameter('tenant'));

        abort_unless($tenant !== null && $panel->canAccessTenant($request->user(), $tenant), 404);

        $this->panels->enterTenant($tenant);

        // Controllers get their arguments by position, and this one is spent.
        $route->forgetParameter('tenant');

        return $next($request);
    }
}
