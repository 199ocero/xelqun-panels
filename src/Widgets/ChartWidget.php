<?php

namespace Xelqun\Panels\Widgets;

use Xelqun\Panels\Exceptions\PanelDefinitionException;

/**
 * A line, bar or doughnut chart, drawn as SVG in the browser.
 *
 * @api
 */
abstract class ChartWidget extends Widget
{
    /** The colors a reader can tell apart. More series would need colors that clash. */
    private const int MAX_SERIES = 8;

    protected static int $columnSpan = 6;

    /**
     * line, bar or doughnut.
     */
    abstract protected function getType(): string;

    /**
     * For a doughnut, the first dataset's values are the slices.
     *
     * @return array{labels: array<int, string>, datasets: array<int, array{label?: string, data: array<int, int|float>}>}
     */
    abstract protected function getData(): array;

    public function toWidget(): array
    {
        $type = $this->getType();
        $data = $this->getData();

        if (! in_array($type, ['line', 'bar', 'doughnut'], true)) {
            throw new PanelDefinitionException("[{$type}] isn't a chart. Use line, bar or doughnut.");
        }

        if (count($data['datasets']) > self::MAX_SERIES) {
            throw new PanelDefinitionException('A chart can show '.self::MAX_SERIES.' series. Split the rest into another chart.');
        }

        $chart = ['type' => $type, 'labels' => array_map(strval(...), $data['labels']), 'datasets' => array_map(fn (array $dataset): array => [
            'label' => (string) ($dataset['label'] ?? ''),
            'data' => array_map(floatval(...), array_values($dataset['data'])),
        ], $data['datasets'])];

        return $this->widget('chart', ['chart' => $type === 'doughnut' ? $this->foldSlices($chart) : $chart]);
    }

    /**
     * Slices past the last color become one, rather than repeating a color.
     *
     * @param  array{type: string, labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, float>}>}  $chart
     * @return array{type: string, labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, float>}>}
     */
    private function foldSlices(array $chart): array
    {
        $values = $chart['datasets'][0]['data'] ?? [];

        if (count($values) <= self::MAX_SERIES) {
            return $chart;
        }

        $kept = self::MAX_SERIES - 1;
        $chart['labels'] = [...array_slice($chart['labels'], 0, $kept), __('xelqun-panels::resources.other')];
        $chart['datasets'][0]['data'] = [...array_slice($values, 0, $kept), array_sum(array_slice($values, $kept))];

        return $chart;
    }
}
