<?php

namespace Xelqun\Panels\Http\Controllers\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;
use Xelqun\Notifications\Notification;
use Xelqun\Panels\Auth\RegisterForm;
use Xelqun\Panels\Exceptions\PanelDefinitionException;

final class RegisterController extends AuthController
{
    public function show(): Response|RedirectResponse
    {
        $panel = $this->panel();

        if ($panel->allows(auth()->user())) {
            return redirect()->to($panel->getUrl());
        }

        return $this->page(
            __('xelqun-panels::auth.register.heading'),
            __($panel->getLoginUrl() === null ? 'xelqun-panels::auth.register.description' : 'xelqun-panels::auth.register.have_account'),
            $this->form(),
            route($panel->routeName('auth.register.store')),
            __('xelqun-panels::auth.register.submit'),
            [],
            $panel->getLoginUrl() === null ? null : $this->link(__('xelqun-panels::auth.register.sign_in'), $panel->getLoginUrl()),
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $panel = $this->panel();
        $user = $this->newUser();

        // validate() returns only what the form declares, so a posted is_admin goes nowhere.
        $user->forceFill($this->form()->validate($request))->save();

        event(new Registered($user));

        if (! $panel->allows($user)) {
            Notification::make()->title(__('xelqun-panels::auth.register.done'))->success()->send();

            return redirect()->to($panel->getLoginUrl() ?? '/');
        }

        Auth::guard($panel->getAuthGuard())->login($user);
        $request->session()->regenerate();

        return redirect()->to($panel->getUrl());
    }

    private function form(): RegisterForm
    {
        return $this->panel()->getRegistrationForm()::make(null, ['table' => $this->newUser()->getTable()]);
    }

    private function newUser(): Authenticatable&Model
    {
        $panel = $this->panel();
        $guard = Auth::guard($panel->getAuthGuard());
        $provider = $guard instanceof SessionGuard ? $guard->getProvider() : null;

        if (! $provider instanceof EloquentUserProvider) {
            throw new PanelDefinitionException("The panel [{$panel->getId()}] needs a session guard on an Eloquent user provider to register users.");
        }

        /** @var Authenticatable&Model */
        return $provider->createModel();
    }
}
