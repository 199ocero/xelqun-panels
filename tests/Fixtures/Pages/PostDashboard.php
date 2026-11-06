<?php

namespace Xelqun\Panels\Tests\Fixtures\Pages;

use Xelqun\Panels\Pages\Dashboard;

/**
 * At its own URL, because Home already has the panel's root.
 */
class PostDashboard extends Dashboard
{
    protected static ?string $slug = 'dashboard';
}
