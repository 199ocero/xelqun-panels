<?php

namespace Xelqun\Panels\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Xelqun\Panels\Panels;

/**
 * Finds the user's tenants by name, for a switcher with more than it lists.
 */
final class TenantSearchController
{
    public function __invoke(Request $request, Panels $panels): JsonResponse
    {
        /** @var array{q?: string|null} $input */
        $input = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $panel = $panels->current();
        $needle = mb_strtolower(trim((string) ($input['q'] ?? '')));

        return response()->json(['tenants' => $panel->toTenantOptions(
            $panel->getTenantsFor($request->user())->filter(fn (Model $tenant): bool => str_contains(mb_strtolower((string) $tenant->getAttribute('name')), $needle)),
        )]);
    }
}
