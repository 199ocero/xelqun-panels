<?php

namespace Xelqun\Panels\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Xelqun\Notifications\Notification;
use Xelqun\Panels\Auth\EditProfileForm;
use Xelqun\Panels\Auth\ProfileActions;
use Xelqun\Panels\Contracts\HasAppAuthentication;
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
            'hasWidgets' => false,
            'tabs' => [],
            'subNavigation' => [],
            'headerActions' => $this->canUseTwoFactor($request) ? ProfileActions::make($request->user())->toSchema() : null,
            'notice' => $this->recoveryCodesNotice(),
            'content' => [$form->toArray()],
        ]]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->form($request)->save($request);

        Notification::make()->title(__('xelqun-panels::auth.profile.saved'))->success()->send();

        return redirect()->route($this->panel()->routeName('auth.profile'));
    }

    private function canUseTwoFactor(Request $request): bool
    {
        return $this->panel()->hasMultiFactorAuthentication() && $request->user() instanceof HasAppAuthentication;
    }

    /**
     * Once, right after they're made. Only their hashes are kept.
     *
     * @return array{title: string, description: string, lines: array<int, string>}|null
     */
    private function recoveryCodesNotice(): ?array
    {
        $codes = session(ProfileActions::RECOVERY_CODES);

        return is_array($codes) ? [
            'title' => __('xelqun-panels::auth.mfa.codes_title'),
            'description' => __('xelqun-panels::auth.mfa.codes_description'),
            'lines' => $codes,
        ] : null;
    }

    private function form(Request $request): EditProfileForm
    {
        return $this->panel()->getProfileForm()::make($request->user());
    }
}
