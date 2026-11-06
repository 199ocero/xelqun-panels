<?php

namespace Xelqun\Panels\Tests\Fixtures\Widgets;

use RuntimeException;
use Xelqun\Panels\Widgets\Widget;

class HiddenWidget extends Widget
{
    public static function canView(): bool
    {
        return false;
    }

    protected function getType(): string
    {
        return 'app/hidden';
    }

    protected function getData(): array
    {
        throw new RuntimeException('A hidden widget must not work out its data.');
    }
}
