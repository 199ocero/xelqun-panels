<?php

namespace Xelqun\Panels\Infolists;

use Xelqun\Tables\Columns\Column;
use Xelqun\Tables\Columns\TextColumn;

/**
 * @method static badge(bool $condition = true)
 * @method static money(string $currency, ?string $locale = null)
 * @method static date(string $format = 'M j, Y')
 * @method static dateTime(string $format = 'M j, Y H:i')
 *
 * @api
 */
class TextEntry extends Entry
{
    protected static function makeColumn(string $name): Column
    {
        return TextColumn::make($name);
    }
}
