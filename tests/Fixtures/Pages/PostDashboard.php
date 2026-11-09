<?php

namespace Xelqun\Panels\Tests\Fixtures\Pages;

use Xelqun\Forms\Components\Select;
use Xelqun\Panels\Pages\Dashboard;
use Xelqun\Panels\Tests\Fixtures\Widgets\FilterEcho;

/**
 * At its own URL, because Home already has the panel's root.
 */
class PostDashboard extends Dashboard
{
    protected static ?string $slug = 'dashboard';

    public function filters(): array
    {
        return [Select::make('team')->options(['red' => 'Red', 'blue' => 'Blue'])->default('red')];
    }

    protected function getFooterWidgets(): array
    {
        return [FilterEcho::class];
    }
}
