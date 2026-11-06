<?php

namespace Xelqun\Panels\Pages;

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

    /**
     * @return array<int, class-string<PanelWidget>>
     */
    protected function getWidgets(): array
    {
        return app(Panels::class)->current()->getWidgets();
    }
}
