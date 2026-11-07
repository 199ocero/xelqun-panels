<?php

namespace Xelqun\Panels\Tests\Fixtures\Widgets;

use Xelqun\Panels\Widgets\Stat;
use Xelqun\Panels\Widgets\StatsOverview;

class RecordStats extends StatsOverview
{
    protected function getStats(): array
    {
        return [Stat::make('Title', (string) $this->record?->getAttribute('title'))];
    }
}
