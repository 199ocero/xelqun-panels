<?php

namespace Xelqun\Panels\Pages;

use Inertia\Inertia;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Widgets\PanelWidget;

/**
 * The panel's home. It shows the widgets from Panel::widgets(). Extend it to
 * change the heading or the widgets.
 *
 * @api
 */
class Dashboard extends Page
{
    protected static ?string $slug = '';

    protected static ?string $navigationIcon = 'house';

    protected static int $navigationSort = -1;

    protected function hasWidgets(): bool
    {
        return true;
    }

    /**
     * @return array<int, class-string<PanelWidget>>
     */
    protected function getWidgets(): array
    {
        return app(Panels::class)->current()->getWidgets();
    }

    /**
     * Deferred, so the page paints at once and a slow query only holds back the cards.
     */
    protected function getProps(): array
    {
        return ['widgets' => Inertia::defer(fn (): array => collect($this->getWidgets())
            ->filter(fn (string $widget): bool => $widget::canView())
            ->sortBy(fn (string $widget): int => $widget::getSort())
            // One failing widget shouldn't take the others down. rescue() reports it.
            ->map(fn (string $widget): ?array => rescue(fn (): array => $widget::make()->toWidget()))
            ->filter()
            ->values()
            ->all())];
    }
}
