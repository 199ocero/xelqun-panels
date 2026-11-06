<?php

namespace Xelqun\Panels\Tests\Fixtures\Widgets;

use Xelqun\Panels\Widgets\ChartWidget;

class SalesChart extends ChartWidget
{
    protected static ?string $heading = 'Sales';

    public static string $type = 'line';

    /** @var array<string, mixed> */
    public static array $data = ['labels' => ['Jan', 'Feb'], 'datasets' => [['label' => 'Orders', 'data' => [1, '2']]]];

    protected function getType(): string
    {
        return self::$type;
    }

    protected function getData(): array
    {
        return self::$data;
    }
}
