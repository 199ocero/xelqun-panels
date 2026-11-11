<?php

namespace Xelqun\Panels\Widgets;

use Xelqun\Tables\Table;

/**
 * A full table on a dashboard. Its rows and actions come through the table
 * endpoints, which ask the table's own authorize(), not canView(), so have the
 * table ask whatever canView() does.
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
