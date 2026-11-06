<?php

namespace Xelqun\Panels\Contracts;

use Xelqun\Panels\Panel;

/**
 * Put this on your user model to say who may enter a panel. A user model
 * without it can't enter any panel, in any environment.
 *
 * @api
 */
interface PanelUser
{
    public function canAccessPanel(Panel $panel): bool;
}
