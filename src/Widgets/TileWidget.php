<?php

namespace Xelqun\Panels\Widgets;

/**
 * A grid of small cards, each with a status.
 *
 * @api
 */
abstract class TileWidget extends Widget
{
    /**
     * @return array<int, Tile>
     */
    abstract protected function getTiles(): array;

    protected function getType(): string
    {
        return 'tiles';
    }

    protected function getData(): array
    {
        return ['tiles' => array_map(fn (Tile $tile): array => $tile->toArray(), $this->getTiles())];
    }
}
