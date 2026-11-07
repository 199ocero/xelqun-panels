<?php

namespace Xelqun\Panels\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Xelqun\Panels\Panels;

/**
 * A panel with tenants has no home of its own, so its path leads to the user's
 * first tenant.
 */
final class RedirectToTenantController
{
    public function __invoke(Request $request, Panels $panels): RedirectResponse
    {
        $panel = $panels->current();
        $tenant = $panel->getTenantsFor($request->user())->first();

        if ($tenant === null && $panel->getTenantRegistrationForm() !== null) {
            return redirect()->route($panel->routeName('tenant.register'));
        }

        abort_if($tenant === null, 403);

        return redirect()->to($panel->getTenantUrl($tenant));
    }
}
