<?php

namespace Xelqun\Panels\Tests\Fixtures;

use Xelqun\Panels\Panel;
use Xelqun\Panels\PanelProvider;
use Xelqun\Panels\Tests\Fixtures\Clusters\Billing;
use Xelqun\Panels\Tests\Fixtures\Clusters\SiteResource;
use Xelqun\Panels\Tests\Fixtures\Clusters\Team;
use Xelqun\Panels\Tests\Fixtures\Pages\Home;
use Xelqun\Panels\Tests\Fixtures\Pages\PartlyDenied;
use Xelqun\Panels\Tests\Fixtures\Pages\PostDashboard;
use Xelqun\Panels\Tests\Fixtures\Pages\Reports;
use Xelqun\Panels\Tests\Fixtures\Resources\NoteResource;
use Xelqun\Panels\Tests\Fixtures\Resources\OtherPostResource;
use Xelqun\Panels\Tests\Fixtures\Resources\PostCommentResource;
use Xelqun\Panels\Tests\Fixtures\Resources\PostResource;
use Xelqun\Panels\Tests\Fixtures\Resources\TaskResource;
use Xelqun\Panels\Tests\Fixtures\Resources\UnguardedResource;
use Xelqun\Panels\Tests\Fixtures\Widgets\BrokenWidget;
use Xelqun\Panels\Tests\Fixtures\Widgets\DeniedList;
use Xelqun\Panels\Tests\Fixtures\Widgets\HiddenWidget;
use Xelqun\Panels\Tests\Fixtures\Widgets\PendingPosts;
use Xelqun\Panels\Tests\Fixtures\Widgets\PostStats;
use Xelqun\Panels\Tests\Fixtures\Widgets\PostTiles;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->id('admin')->path('admin')->brandName('Admin')->authGuard('admin')->login()->passwordReset()->registration()->profile()->databaseNotifications()->multiFactorAuthentication()->emailAuthentication()->pages([Home::class, PostDashboard::class, PartlyDenied::class, Reports::class, Billing::class, Team::class])->resources([SiteResource::class, PostResource::class, OtherPostResource::class, UnguardedResource::class, NoteResource::class, TaskResource::class, PostCommentResource::class])->widgets([PostTiles::class, PendingPosts::class, PostStats::class, HiddenWidget::class, BrokenWidget::class, DeniedList::class]);
    }
}
