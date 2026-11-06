<?php

namespace Xelqun\Panels\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Xelqun\Notifications\Notification;
use Xelqun\Panels\Auth\EditProfileForm;
use Xelqun\Panels\Pages\FormContent;

final class ProfileController extends AuthController
{
    public function show(Request $request): Response
    {
        $title = __('xelqun-panels::auth.profile.heading');
        $form = new FormContent($this->form($request), route($this->panel()->routeName('auth.profile.update')), 'put', __('xelqun-panels::auth.profile.submit'));

        return Inertia::render('xelqun/Panel', ['page' => [
            'title' => $title,
            'eyebrow' => null,
            'subheading' => null,
            'breadcrumbs' => [['label' => $title]],
            'headerActions' => null,
            'content' => [$form->toArray()],
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
