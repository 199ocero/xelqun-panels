<?php

namespace Xelqun\Panels\Widgets;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Xelqun\Tables\Columns\TextColumn;
use Xelqun\Tables\Table;

/**
 * Rows of records, each with an icon, a title, a line of detail and either
 * actions, a progress bar or a link. It's a table underneath, so query() limits
 * what it can reach, and every row action is rebuilt and checked on the server.
 *
 * Define query(), title() and any of the others. Name the model, as in
 * `@extends ListWidget<Product>`, and each of them gets a Product.
 *
 * @template TModel of Model = Model
 *
 * @api
 */
abstract class ListWidget extends Table implements PanelWidget
{
    use HasWidgetSettings;

    /** How many rows to show. */
    protected static int $limit = 5;

    protected function boot(): void
    {
        $this->id($this->getWidgetId())->paginationPageOptions([static::$limit]);
    }

    /**
     * @param  TModel  $record
     */
    abstract protected function title(Model $record): string;

    /**
     * Its rows and actions come through the table endpoints, which ask this, so
     * a widget the user can't see can't be read that way either.
     */
    public function authorize(?Authenticatable $user): bool
    {
        return static::canView();
    }

    /**
     * @param  TModel  $record
     */
    protected function meta(Model $record): ?string
    {
        return null;
    }

    /**
     * @param  TModel  $record
     */
    protected function icon(Model $record): ?string
    {
        return null;
    }

    /**
     * @param  TModel  $record
     * @return 'primary'|'success'|'warning'|'danger'|'gray'
     */
    protected function iconColor(Model $record): string
    {
        return 'gray';
    }

    /**
     * From 0 to 1, or null for no bar.
     *
     * @param  TModel  $record
     */
    protected function progress(Model $record): ?float
    {
        return null;
    }

    /**
     * @param  TModel  $record
     */
    protected function progressNote(Model $record): ?string
    {
        return null;
    }

    final public function columns(): array
    {
        $cell = fn (string $name, callable $state): TextColumn => TextColumn::make($name)->state(fn (Model $record): mixed => $state($record));

        return [
            $cell('title', fn (Model $record): string => $this->title($record)),
            $cell('meta', fn (Model $record): ?string => $this->meta($record)),
            $cell('icon', fn (Model $record): ?string => $this->icon($record)),
            $cell('iconColor', fn (Model $record): string => $this->iconColor($record)),
            $cell('progress', fn (Model $record): ?float => $this->progress($record)),
            $cell('progressNote', fn (Model $record): ?string => $this->progressNote($record)),
        ];
    }

    public function toWidget(): array
    {
        return $this->widget('list', ['table' => $this->toSchema()]);
    }
}
