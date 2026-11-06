<?php

namespace Xelqun\Panels\Widgets;

/**
 * A row of numbers.
 *
 * @api
 */
abstract class StatsOverview extends Widget
{
    /**
     * @return array<int, Stat>
     */
    abstract protected function getStats(): array;

    protected function getType(): string
    {
        return 'stats';
    }

    protected function getData(): array
    {
        return ['stats' => array_map(fn (Stat $stat): array => $stat->toArray(), $this->getStats())];
    }
}
