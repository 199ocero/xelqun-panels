<?php

namespace Xelqun\Panels\Tests\Fixtures;

use Xelqun\Panels\Navigation\NavigationItem;
use Xelqun\Panels\Panel;
use Xelqun\Panels\PanelProvider;
use Xelqun\Panels\Tests\Fixtures\Pages\Home;
use Xelqun\Panels\Tests\Fixtures\Tenancy\ClientResource;
use Xelqun\Panels\Tests\Fixtures\Tenancy\FolderResource;
use Xelqun\Panels\Tests\Fixtures\Tenancy\ProjectResource;
use Xelqun\Panels\Tests\Fixtures\Tenancy\TeamForm;

class TeamPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->id('team')->path('team')->authGuard('admin')->login()->profile()->tenant(Team::class, slugAttribute: 'slug')->middleware([TagsResponse::class.':panel'])->authMiddleware([TagsResponse::class.':auth'])->tenantMiddleware([TagsResponse::class.':tenant'])->tenantMenuItems([NavigationItem::make('Billing')->url('/billing')->icon('credit-card')])->tenantRegistration(TeamForm::class)->tenantProfile(TeamForm::class)->pages([Home::class])->resources([ProjectResource::class, FolderResource::class, ClientResource::class]);
    }
}
