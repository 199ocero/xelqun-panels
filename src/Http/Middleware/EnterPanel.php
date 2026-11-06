<?php

namespace Xelqun\Panels\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Xelqun\Panels\Panels;

/**
 * Runs on every route of a panel. The shell is shared, not passed by each page,
 * so a host's own Vue page in the panel gets it too.
 */
final class EnterPanel
{
    public function __construct(private readonly Panels $panels) {}

    public function handle(Request $request, Closure $next, string $id): Response
    {
        $panel = $this->panels->get($id);

        abort_if($panel === null, 404);

        $this->panels->enter($panel);

        Inertia::setRootView('xelqun-panels::app');
        // Sharing lasts as long as the process, so under Octane this runs for later requests too.
        Inertia::share('xelqunPanel', fn (): ?array => $this->panels->current()?->toShell());

        return $next($request);
    }
}
