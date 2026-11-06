<?php

namespace Xelqun\Panels\Tests\Fixtures\Widgets;

use Xelqun\Panels\Widgets\Tile;
use Xelqun\Panels\Widgets\TileWidget;

class PostTiles extends TileWidget
{
    protected static int $sort = 2;

    protected static int $columnSpan = 4;

    protected static ?string $heading = 'Teams';

    protected function getTiles(): array
    {
        return [
            Tile::make('Red')->icon('tag')->status('Working', 'primary')->url('/teams/red'),
            Tile::make('Blue')->url('javascript:alert(1)'),
        ];
    }
}
