<?php

namespace Xelqun\Panels\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Xelqun\Notifications\Notification;
use Xelqun\Panels\Auth\EditProfileForm;

final class ProfileController extends AuthController
{
    public function show(Request $request): Response
    {
        $panel = $this->panel();
        $form = $this->form($request);
        $schema = $form->toSchema();

        return Inertia::render('xelqun/Panel', ['page' => [
            'title' => __('xelqun-panels::auth.profile.heading'),
            'eyebrow' => null,
            'subheading' => null,
            'breadcrumbs' => [['label' => __('xelqun-panels::auth.profile.heading')]],
            'headerActions' => null,
            'content' => [[
                'type' => 'form',
                'schema' => $schema,
                'action' => route($panel->routeName('auth.profile.update')),
                'method' => 'put',
                'submitLabel' => __('xelqun-panels::auth.profile.submit'),
                // Changes after a save, so the password boxes empty, but not after a failed one.
                'revision' => md5(json_encode([$schema['state'], $form->getRecord()?->getAttribute('updated_at')], JSON_THROW_ON_ERROR)),
            ]],
        ]]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->form($request)->save($request);

        Notification::make()->title(__('xelqun-panels::auth.profile.saved'))->success()->send();

        return redirect()->route($this->panel()->routeName('auth.profile'));
    }

    private function form(Request $request): EditProfileForm
    {
        return $this->panel()->getProfileForm()::make($request->user());
    }
}
