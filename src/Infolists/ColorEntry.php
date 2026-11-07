<?php

namespace Xelqun\Panels\Infolists;

use Xelqun\Tables\Columns\Column;
use Xelqun\Tables\Columns\TextColumn;

/**
 * A color as a swatch, with its value.
 *
 * @api
 */
class ColorEntry extends Entry
{
    protected static function makeColumn(string $name): Column
    {
        return TextColumn::make($name);
    }

    protected function getDisplay(): string
    {
        return 'color';
    }
}
