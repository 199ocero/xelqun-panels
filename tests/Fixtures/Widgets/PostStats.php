<?php

namespace Xelqun\Panels\Tests\Fixtures\Widgets;

use Xelqun\Panels\Tests\Fixtures\Post;
use Xelqun\Panels\Widgets\Stat;
use Xelqun\Panels\Widgets\StatsOverview;

class PostStats extends StatsOverview
{
    protected static int $sort = -1;

    protected function getStats(): array
    {
        return [
            Stat::make('Posts', Post::query()->count())->description('All of them')->icon('file-text')->chart([1, 3, 2]),
        ];
    }
}
