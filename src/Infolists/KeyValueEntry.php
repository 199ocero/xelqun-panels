<?php

namespace Xelqun\Panels\Infolists;

use Illuminate\Database\Eloquent\Model;
use Xelqun\Tables\Columns\Column;
use Xelqun\Tables\Columns\TextColumn;

/**
 * An array of keys and values, as a small table.
 *
 * @api
 */
class KeyValueEntry extends Entry
{
    protected static function makeColumn(string $name): Column
    {
        return TextColumn::make($name);
    }

    protected function getDisplay(): string
    {
        return 'key-value';
    }

    public function toProps(Model $record): array
    {
        $state = $this->getColumn()->getState($record);
        $pairs = [];

        foreach (is_array($state) ? $state : [] as $key => $value) {
            $pairs[] = ['key' => (string) $key, 'value' => is_scalar($value) ? (string) $value : (string) json_encode($value)];
        }

        return [...parent::toProps($record), 'pairs' => $pairs];
    }
}
