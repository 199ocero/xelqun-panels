<?php

namespace Xelqun\Panels\Infolists;

use Illuminate\Database\Eloquent\Model;
use Xelqun\Tables\Columns\Column;
use Xelqun\Tables\Columns\TextColumn;

/**
 * Text in a monospaced block. An array shows as JSON.
 *
 * @api
 */
class CodeEntry extends Entry
{
    protected static function makeColumn(string $name): Column
    {
        return TextColumn::make($name)->formatStateUsing(fn (mixed $state): mixed => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state);
    }

    protected function getDisplay(): string
    {
        return 'code';
    }

    public function toProps(Model $record): array
    {
        $state = $this->getColumn()->getState($record);

        return [...parent::toProps($record), 'text' => is_array($state) ? (string) json_encode($state, JSON_PRETTY_PRINT) : (string) $state];
    }
}
