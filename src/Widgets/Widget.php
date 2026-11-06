<?php

namespace Xelqun\Panels\Widgets;

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

    final public function __construct() {}

    public static function make(): static
    {
        return new static;
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
