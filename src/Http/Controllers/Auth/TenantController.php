<?php

namespace Xelqun\Panels\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Xelqun\Notifications\Notification;
use Xelqun\Panels\Pages\FormContent;

/**
 * Starting a tenant, and changing one. Changing takes `update` in the tenant's
 * policy, so with no policy nobody can.
 */
final class TenantController extends AuthController
{
    public function showRegistration(): Response
    {
        $panel = $this->panel();

        return $this->page(
            __('xelqun-panels::auth.tenant.register_heading'),
            null,
            $panel->getTenantRegistrationForm()::make(),
            route($panel->routeName('tenant.register.store')),
            __('xelqun-panels::auth.tenant.register_submit'),
        );
    }

    public function register(Request $request): RedirectResponse
    {
        $panel = $this->panel();
        $tenant = $panel->getTenantRegistrationForm()::make()->save($request);

        return redirect()->to($panel->canAccessTenant($request->user(), $tenant) ? $panel->getTenantUrl($tenant) : $panel->getUrl());
    }

    public function showProfile(): Response
    {
        $panel = $this->panel();
        $tenant = $this->panels->tenant();

        abort_unless(Gate::allows('update', $tenant), 403);

        return $this->panelPage(__('xelqun-panels::auth.tenant.profile_heading'), [
            'content' => [(new FormContent(
                $panel->getTenantProfileForm()::make($tenant),
                route($panel->routeName('tenant.profile.update')),
                'put',
                __('xelqun-panels::auth.profile.submit'),
            ))->toArray()],
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $panel = $this->panel();
        $tenant = $this->panels->tenant();

        abort_unless(Gate::allows('update', $tenant), 403);

        $saved = $panel->getTenantProfileForm()::make($tenant)->save($request);

        Notification::make()->title(__('xelqun-panels::auth.profile.saved'))->success()->send();

        return redirect()->to($panel->getTenantUrl($saved));
    }
}
