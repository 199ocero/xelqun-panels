<?php

namespace Xelqun\Panels;

use Illuminate\Support\ServiceProvider;

/**
 * Declares a panel. Add a subclass to bootstrap/providers.php.
 *
 * @api
 */
abstract class PanelProvider extends ServiceProvider
{
    abstract public function panel(Panel $panel): Panel;

    public function register(): void
    {
        // Here as well as in the package's provider, because either can run first.
        $this->app->singletonIf(Panels::class);
        $this->app->make(Panels::class)->register($this->panel(Panel::make()));
    }
}
