<?php

namespace Xelqun\Panels\Tests\Fixtures\Widgets;

use Xelqun\Panels\Widgets\Widget;

/**
 * Shows back the dashboard's filters, below the content, and asks to reload.
 */
class FilterEcho extends Widget
{
    protected static ?int $pollingInterval = 30;

    protected function getType(): string
    {
        return 'acme/filter-echo';
    }

    protected function getData(): array
    {
        return ['filters' => $this->pageFilters];
    }
}
