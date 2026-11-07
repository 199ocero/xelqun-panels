<?php

namespace Xelqun\Panels\Tests\Fixtures\Clusters;

use Xelqun\Panels\Pages\Page;

class Billing extends Page
{
    protected static ?string $cluster = Settings::class;

    protected static int $navigationSort = 1;
}
