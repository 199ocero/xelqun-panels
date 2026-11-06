<?php

namespace Xelqun\Panels\Widgets;

/**
 * Anything a dashboard can hold. Widget covers most cases. ListWidget is a table
 * underneath, so it can't extend that class.
 *
 * @api
 */
interface PanelWidget
{
    public static function make(): static;

    /**
     * Whether this user sees it. A hidden widget's data is never worked out.
     */
    public static function canView(): bool;

    public static function getSort(): int;

    /**
     * @return array{type: string, id: string, columnSpan: int, data: array<string, mixed>}
     */
    public function toWidget(): array;
}
