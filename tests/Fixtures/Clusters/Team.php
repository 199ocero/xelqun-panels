<?php

namespace Xelqun\Panels\Tests\Fixtures\Clusters;

use Xelqun\Panels\Pages\Page;

class Team extends Page
{
    protected static ?string $slug = 'team-settings';

    protected static ?string $cluster = Settings::class;

    protected static int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return config('testing.team_settings', true);
    }
}
