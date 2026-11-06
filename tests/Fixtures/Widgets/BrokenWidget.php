<?php

namespace Xelqun\Panels\Tests\Fixtures\Widgets;

use RuntimeException;
use Xelqun\Panels\Widgets\Widget;

class BrokenWidget extends Widget
{
    public static bool $breaks = false;

    public static function canView(): bool
    {
        return self::$breaks;
    }

    protected function getType(): string
    {
        return 'app/broken';
    }

    protected function getData(): array
    {
        throw new RuntimeException('This widget is broken.');
    }
}
