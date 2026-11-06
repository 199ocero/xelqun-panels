<?php

namespace Xelqun\Panels\Tests\Fixtures;

use Xelqun\Panels\Panel;
use Xelqun\Panels\PanelProvider;
use Xelqun\Panels\Tests\Fixtures\Pages\Home;
use Xelqun\Panels\Tests\Fixtures\Pages\PartlyDenied;
use Xelqun\Panels\Tests\Fixtures\Pages\Reports;
use Xelqun\Panels\Tests\Fixtures\Resources\OtherPostResource;
use Xelqun\Panels\Tests\Fixtures\Resources\PostResource;
use Xelqun\Panels\Tests\Fixtures\Resources\UnguardedResource;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->id('admin')->path('admin')->brandName('Admin')->authGuard('admin')->login()->passwordReset()->registration()->profile()->pages([Home::class, PartlyDenied::class, Reports::class])->resources([PostResource::class, OtherPostResource::class, UnguardedResource::class]);
    }
}
