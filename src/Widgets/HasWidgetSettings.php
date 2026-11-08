<?php

namespace Xelqun\Panels\Widgets;

use Illuminate\Support\Str;

/**
 * What every widget shares.
 *
 * @api
 */
trait HasWidgetSettings
{
    protected static int $sort = 0;

    /** Out of 12. */
    protected static int $columnSpan = 12;

    protected static ?string $heading = null;

    public static function canView(): bool
    {
        return true;
    }

    public static function getSort(): int
    {
        return static::$sort;
    }

    /**
     * Where "See all" goes. A method, because route() can't be a property's default.
     */
    protected function getSeeAllUrl(): ?string
    {
        return null;
    }

    protected function getWidgetId(): string
    {
        return Str::kebab(class_basename(static::class));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, id: string, columnSpan: int, data: array<string, mixed>}
     */
    protected function widget(string $type, array $data): array
    {
        return [
            'type' => $type,
            'id' => $this->getWidgetId(),
            'columnSpan' => static::$columnSpan,
            // Left out when unset, so a null can't override the heading a custom widget gives its card.
            'data' => [...array_filter(['heading' => static::$heading, 'seeAllUrl' => $this->getSeeAllUrl()], fn (mixed $value): bool => $value !== null), ...$data],
        ];
    }
}
