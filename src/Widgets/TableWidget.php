<?php

namespace Xelqun\Panels\Widgets;

use Xelqun\Tables\Table;

/**
 * A full table on a dashboard.
 *
 * @api
 */
abstract class TableWidget extends Widget
{
    abstract protected function getTable(): Table;

    protected function getType(): string
    {
        return 'table';
    }

    protected function getData(): array
    {
        return ['table' => $this->getTable()->toSchema()];
    }
}
