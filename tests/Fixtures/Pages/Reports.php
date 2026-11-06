<?php

namespace Xelqun\Panels\Tests\Fixtures\Pages;

use Xelqun\Panels\Pages\Page;

class Reports extends Page
{
    protected function getComponent(): string
    {
        return 'reports/Index';
    }

    protected function getProps(): array
    {
        return ['total' => 3];
    }
}
