<?php

namespace Xelqun\Panels\Widgets;

use Illuminate\Database\Eloquent\Model;

/**
 * Something on a dashboard that isn't a table. For one with its own Vue
 * component, return a type from getType() that the app registers with
 * createXelqunForms({ components }).
 *
 * @api
 */
abstract class Widget implements PanelWidget
{
    use HasWidgetSettings;

    /** The record of the page it's on, if that's a record's page. */
    protected ?Model $record = null;

    final public function __construct() {}

    public static function make(): static
    {
        return new static;
    }

    public function forRecord(?Model $record): static
    {
        $this->record = $record;

        return $this;
    }

    abstract protected function getType(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function getData(): array;

    public function toWidget(): array
    {
        return $this->widget($this->getType(), $this->getData());
    }
}
