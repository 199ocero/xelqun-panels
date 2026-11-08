<?php

namespace Xelqun\Panels\Commands\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Generated files start from a column the table really has, so they work before
 * anyone edits them. Without a table to read, they use `name`.
 */
trait GuessesTitleColumn
{
    private const array TITLE_COLUMNS = ['name', 'title', 'label', 'number', 'email', 'slug'];

    private function guessTitleColumn(?Model $model): string
    {
        try {
            $columns = $model === null ? [] : Schema::getColumns($model->getTable());
        } catch (Throwable) {
            $columns = [];
        }

        $names = array_column($columns, 'name');
        $text = array_column(array_filter($columns, fn (array $column): bool => preg_match('/char|text|string/i', (string) $column['type_name']) === 1), 'name');

        return array_find(self::TITLE_COLUMNS, fn (string $name): bool => in_array($name, $names, true)) ?? $text[0] ?? 'name';
    }
}
