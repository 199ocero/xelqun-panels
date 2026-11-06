<?php

namespace Xelqun\Panels\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

final class LogoutController extends AuthController
{
    /**
     * shortcut: invalidating ends every guard in this session, so signing out of one panel signs out of the others. Forget only this guard's session keys if an app needs them apart.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $panel = $this->panel();

        Auth::guard($panel->getAuthGuard())->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // The pages in the back button held records.
        Inertia::clearHistory();

        return redirect()->to($panel->getLoginUrl() ?? '/');
    }
}
