<?php

namespace Xelqun\Panels\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Xelqun\Panels\Panels;

/**
 * Finds records for the ⌘K palette, across the panel's resources.
 */
final class GlobalSearchController
{
    public function __invoke(Request $request, Panels $panels): JsonResponse
    {
        /** @var array{q: string} $input */
        $input = $request->validate(['q' => ['required', 'string', 'max:100']]);

        $groups = collect($panels->current()->getResources())
            ->filter(fn (string $resource): bool => $resource::can('viewAny'))
            ->map(fn (string $resource): array => [
                'label' => $resource::getNavigationLabel(),
                'icon' => $resource::getNavigationIcon(),
                'results' => $resource::getGlobalSearchResults($input['q']),
            ])
            ->filter(fn (array $group): bool => $group['results'] !== [])
            ->values();

        return response()->json(['groups' => $groups]);
    }
}
