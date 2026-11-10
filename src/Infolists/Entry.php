<?php

namespace Xelqun\Panels\Infolists;

use Closure;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Xelqun\Forms\Components\Component;
use Xelqun\Tables\Columns\Column;

/**
 * One value of a record on a view page. It sits in forms' layouts, such as
 * Section and Grid, and takes the table column's options, so a value reads the
 * same in both places.
 *
 * @method static label(string|Closure|null $label)
 * @method static state(?Closure $using)
 * @method static formatStateUsing(?Closure $using)
 * @method static color(string|Closure|null $color)
 * @method static icon(string|Closure|null $icon)
 * @method static url(string|Closure|null $url, bool $openInNewTab = false)
 *
 * @api
 */
abstract class Entry extends Component
{
    private Column $column;

    protected string|Closure|null $hint = null;

    protected bool|Closure $hasInlineLabel = false;

    final public function __construct(string $name)
    {
        $this->column = static::makeColumn($name);
    }

    public static function make(string $name): static
    {
        return (new static($name))->configure();
    }

    abstract protected static function makeColumn(string $name): Column;

    public function getType(): string
    {
        return 'xelqun/entry';
    }

    /**
     * @param  string  $method
     * @param  array<int, mixed>  $parameters
     */
    public function __call($method, $parameters): mixed
    {
        if (! method_exists($this->column, $method)) {
            return parent::__call($method, $parameters);
        }

        $this->column->{$method}(...$parameters);

        return $this;
    }

    /**
     * A short note beside the label.
     */
    public function hint(string|Closure|null $hint): static
    {
        $this->hint = $hint;

        return $this;
    }

    /**
     * Put the label beside the value instead of above it.
     */
    public function inlineLabel(bool|Closure $condition = true): static
    {
        $this->hasInlineLabel = $condition;

        return $this;
    }

    protected function getColumn(): Column
    {
        return $this->column;
    }

    /**
     * How the browser draws it. Empty means by the column's type.
     */
    protected function getDisplay(): string
    {
        return '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toProps(Model $record): array
    {
        return [
            'display' => $this->getDisplay(),
            'column' => $this->column->toDefinition(),
            'cell' => $this->column->toCell($record),
            ...array_filter([
                'hint' => $this->evaluateFor($this->hint, $record),
                'inlineLabel' => (bool) $this->evaluateFor($this->hasInlineLabel, $record),
            ]),
        ];
    }

    /**
     * A closure gets $record, like a column's. An entry can be drawn outside a form, where evaluate() can't run.
     */
    private function evaluateFor(mixed $value, Model $record): mixed
    {
        return $value instanceof Closure ? app()->call($value, ['record' => $record]) : $value;
    }

    protected function getProps(): array
    {
        $record = $this->getForm()->getRecord();

        if (! $record instanceof Model) {
            throw new LogicException('An entry shows a record, and this form has none.');
        }

        return $this->toProps($record);
    }
}
