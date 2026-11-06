<?php

namespace Xelqun\Panels\Infolists;

use Xelqun\Tables\Columns\Column;
use Xelqun\Tables\Columns\ImageColumn;

/**
 * @method static disk(?string $disk)
 * @method static size(int $pixels)
 * @method static circular(bool $condition = true)
 *
 * @api
 */
class ImageEntry extends Entry
{
    protected static function makeColumn(string $name): Column
    {
        return ImageColumn::make($name);
    }
}
