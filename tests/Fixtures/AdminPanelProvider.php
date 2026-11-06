<?php

namespace Xelqun\Panels\Tests\Fixtures;

use Xelqun\Panels\Panel;
use Xelqun\Panels\PanelProvider;
use Xelqun\Panels\Tests\Fixtures\Pages\Home;
use Xelqun\Panels\Tests\Fixtures\Pages\PartlyDenied;
use Xelqun\Panels\Tests\Fixtures\Pages\Reports;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->id('admin')->path('admin')->brandName('Admin')->authGuard('admin')->login()->pages([Home::class, PartlyDenied::class, Reports::class]);
    }
}
