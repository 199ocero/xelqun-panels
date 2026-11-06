<?php

namespace Xelqun\Panels\Contracts;

use Xelqun\Panels\Panel;

/**
 * A bundle of resources, pages and settings for a panel. Add one with
 * Panel::plugin().
 *
 * @api
 */
interface Plugin
{
    public function getId(): string;

    /**
     * Runs as the panel is declared, so add its resources and pages here.
     */
    public function register(Panel $panel): void;

    /**
     * Runs when the app has booted, so everything else is available.
     */
    public function boot(Panel $panel): void;
}
