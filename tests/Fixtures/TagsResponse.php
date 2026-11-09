<?php

namespace Xelqun\Panels\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Names each group of panel middleware a request went through, in order.
 */
class TagsResponse
{
    public function handle(Request $request, Closure $next, string $tag): Response
    {
        $response = $next($request);
        $seen = $response->headers->get('X-Xq-Middleware');

        // Inner middleware answers first, so put this tag in front.
        $response->headers->set('X-Xq-Middleware', $seen === null ? $tag : "{$tag},{$seen}");

        return $response;
    }
}
