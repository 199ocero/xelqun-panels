<?php

namespace Xelqun\Panels\Infolists;

use Xelqun\Tables\Columns\Column;
use Xelqun\Tables\Columns\IconColumn;

/**
 * @method static boolean(bool $condition = true)
 *
 * @api
 */
class IconEntry extends Entry
{
    protected static function makeColumn(string $name): Column
    {
        return IconColumn::make($name);
    }
}
