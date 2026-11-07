<?php

namespace Xelqun\Panels\Infolists;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Xelqun\Tables\Columns\Column;
use Xelqun\Tables\Columns\TextColumn;

/**
 * Entries repeated for each item of a relationship or an array attribute.
 *
 * @api
 */
class RepeatableEntry extends Entry
{
    /** @var array<int, Entry> */
    private array $entries = [];

    protected static function makeColumn(string $name): Column
    {
        return TextColumn::make($name);
    }

    protected function getDisplay(): string
    {
        return 'repeatable';
    }

    /**
     * Kept here, not as child components, because each row has its own record.
     *
     * @param  array<int, Entry>  $components
     */
    public function schema(array $components): static
    {
        $this->entries = $components;

        return $this;
    }

    public function toProps(Model $record): array
    {
        $name = $this->getColumn()->getName();
        $items = $record->getAttribute($name) ?? [];
        $items = $items instanceof Collection ? $items->all() : (array) $items;

        return [...parent::toProps($record), 'rows' => array_map(
            fn (mixed $item): array => array_map(fn (Entry $entry): array => $entry->toProps($this->toRecord($item)), $this->entries),
            array_values($items),
        )];
    }

    /**
     * An array row gets a record, so entries read it the same way.
     */
    private function toRecord(mixed $item): Model
    {
        return $item instanceof Model ? $item : (new class extends Model {})->forceFill((array) $item);
    }
}
