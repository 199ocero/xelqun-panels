<?php

namespace Xelqun\Panels\Tests\Fixtures;

use Xelqun\Panels\Panel;
use Xelqun\Panels\PanelProvider;
use Xelqun\Panels\Tests\Fixtures\Pages\Home;

class StaffPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->id('staff')->path('/staff/')->pages([Home::class]);
    }
}
