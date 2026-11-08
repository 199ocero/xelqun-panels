<?php

namespace Xelqun\Panels\Testing;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Xelqun\Panels\Panels;

/**
 * For tests of an app's panels. Use it on your test case or with `uses()`.
 *
 * @api
 */
trait InteractsWithPanels
{
    /**
     * Sign in on a panel's own guard, and be inside the panel, so getUrl() and
     * the panel's tables and forms work in the test as they do in a request.
     */
    public function actingInPanel(Authenticatable $user, string $id): static
    {
        $this->enterPanel($id);

        return $this->actingAs($user, app(Panels::class)->get($id)->getAuthGuard());
    }

    /**
     * Be inside a panel and, if it has them, a tenant, as a request would be. It's
     * needed before building a table or form to test, so its token gets their scope.
     */
    public function enterPanel(string $id, ?Model $tenant = null): static
    {
        $panels = app(Panels::class);

        $panels->enter($panels->get($id));

        if ($tenant !== null) {
            $panels->enterTenant($tenant);
        }

        return $this;
    }
}
