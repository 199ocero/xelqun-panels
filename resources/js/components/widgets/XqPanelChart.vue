<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { PanelChart } from '../../types';

const HEIGHT = 240;
const LEFT = 48;
const TOP = 12;
const BOTTOM = 26;
const RIGHT = 12;
const MAX_BAR_WIDTH = 24;
const MAX_X_LABELS = 8;
const TICKS = 4;
const DOUGHNUT_RADIUS = 80;
const DOUGHNUT_WIDTH = 24;
/** A 2px surface gap between neighbouring slices. */
const SLICE_GAP = 2;

const props = defineProps<{ chart: PanelChart }>();

const root = ref<HTMLElement>();
const width = ref(640);
const hovered = ref<number | null>(null);

const color = (index: number): string => `var(--xq-chart-${index + 1})`;
const format = (value: number): string =>
    value.toLocaleString(undefined, { maximumFractionDigits: 2 });

const plotWidth = computed(() => Math.max(width.value - LEFT - RIGHT, 1));
const plotHeight = HEIGHT - TOP - BOTTOM;
const count = computed(() => props.chart.labels.length);
const values = computed(() => props.chart.datasets.flatMap((dataset) => dataset.data));

/** The smallest round step that fits the range in about four gridlines, so the axis reads 0, 10, 20. */
function roundStep(range: number): number {
    if (range <= 0) {
        return 1;
    }

    const rough = range / TICKS;
    const magnitude = 10 ** Math.floor(Math.log10(rough));

    return ([1, 2, 5, 10].find((candidate) => candidate * magnitude >= rough) ?? 10) * magnitude;
}

const domain = computed(() => {
    const highest = Math.max(0, ...values.value);
    const lowest = Math.min(0, ...values.value);
    const step = roundStep(highest - lowest);

    return {
        step,
        high: Math.max(Math.ceil(highest / step), 1) * step,
        low: Math.floor(lowest / step) * step,
    };
});

const y = (value: number): number =>
    TOP +
    plotHeight -
    ((value - domain.value.low) / (domain.value.high - domain.value.low)) * plotHeight;

const ticks = computed(() => {
    const { high, low, step } = domain.value;

    return Array.from({ length: Math.round((high - low) / step) + 1 }, (_, index) => {
        const value = low + step * index;

        return { value, y: y(value) };
    });
});

const band = computed(() => plotWidth.value / Math.max(count.value, 1));
const center = (index: number): number => LEFT + band.value * (index + 0.5);

const xLabels = computed(() => {
    const step = Math.ceil(
        count.value / Math.min(MAX_X_LABELS, Math.floor(plotWidth.value / 56) || 1),
    );

    return props.chart.labels
        .map((label, index) => ({ label, index }))
        .filter(({ index }) => index % step === 0);
});

const lines = computed(() =>
    props.chart.datasets.map((dataset) =>
        dataset.data.map((value, index) => `${center(index)},${y(value)}`).join(' '),
    ),
);

const barWidth = computed(() => {
    const seriesCount = Math.max(props.chart.datasets.length, 1);

    return Math.min(MAX_BAR_WIDTH, Math.max((band.value * 0.7) / seriesCount - SLICE_GAP, 2));
});

const bars = computed(() => {
    const seriesCount = props.chart.datasets.length;
    const group = seriesCount * barWidth.value + (seriesCount - 1) * SLICE_GAP;

    return props.chart.datasets.flatMap((dataset, series) =>
        dataset.data.map((value, index) => ({
            key: `${series}-${index}`,
            index,
            series,
            x: center(index) - group / 2 + series * (barWidth.value + SLICE_GAP),
            y: Math.min(y(value), y(0)),
            height: Math.abs(y(value) - y(0)),
        })),
    );
});

const slices = computed(() => {
    const data = props.chart.datasets[0]?.data ?? [];
    const total = data.reduce((sum, value) => sum + Math.max(value, 0), 0);
    const circumference = 2 * Math.PI * DOUGHNUT_RADIUS;
    let offset = 0;

    return data.map((value, index) => {
        const length = total === 0 ? 0 : (Math.max(value, 0) / total) * circumference;
        const slice = {
            index,
            dash: `${Math.max(length - SLICE_GAP, 0)} ${circumference}`,
            offset: -offset,
        };

        offset += length;

        return slice;
    });
});

const total = computed(() =>
    (props.chart.datasets[0]?.data ?? []).reduce((sum, value) => sum + value, 0),
);

/** A doughnut names its slices, and any other chart its series. */
const legend = computed(() =>
    props.chart.type === 'doughnut'
        ? props.chart.labels
        : props.chart.datasets.map((dataset) => dataset.label),
);

const tooltip = computed(() => {
    if (hovered.value === null) {
        return null;
    }

    const index = hovered.value;

    if (props.chart.type === 'doughnut') {
        return {
            title: null,
            rows: [
                {
                    label: props.chart.labels[index],
                    value: props.chart.datasets[0].data[index],
                    series: index,
                },
            ],
            left: '50%',
        };
    }

    return {
        title: props.chart.labels[index],
        rows: props.chart.datasets.map((dataset, series) => ({
            label: dataset.label,
            value: dataset.data[index],
            series,
        })),
        left: `${(center(index) / width.value) * 100}%`,
    };
});

let observer: ResizeObserver | undefined;

onMounted(() => {
    if (root.value && typeof ResizeObserver !== 'undefined') {
        observer = new ResizeObserver(([entry]) => (width.value = entry.contentRect.width));
        observer.observe(root.value);
    }
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div ref="root" class="relative" data-xq-chart>
        <ul
            v-if="legend.length > 1"
            class="mb-3 gap-x-4 gap-y-1 text-xs text-xq-muted-foreground flex flex-wrap"
            data-xq-chart-legend
        >
            <li v-for="(label, index) in legend" :key="label" class="gap-1.5 flex items-center">
                <span
                    aria-hidden="true"
                    class="size-2.5 rounded-sm"
                    :style="{ background: color(index) }"
                ></span>
                {{ label }}
            </li>
        </ul>

        <svg
            v-if="chart.type === 'doughnut'"
            viewBox="0 0 240 200"
            class="h-52 mx-auto block"
            aria-hidden="true"
            @mouseleave="hovered = null"
        >
            <g transform="rotate(-90 120 100)">
                <circle
                    v-for="slice in slices"
                    :key="slice.index"
                    cx="120"
                    cy="100"
                    :r="DOUGHNUT_RADIUS"
                    fill="none"
                    :stroke="color(slice.index)"
                    :stroke-width="DOUGHNUT_WIDTH"
                    :stroke-dasharray="slice.dash"
                    :stroke-dashoffset="slice.offset"
                    :opacity="hovered === null || hovered === slice.index ? 1 : 0.45"
                    data-xq-chart-slice
                    @mouseenter="hovered = slice.index"
                />
            </g>
            <text
                x="120"
                y="106"
                text-anchor="middle"
                class="fill-xq-foreground text-xl font-semibold"
            >
                {{ format(total) }}
            </text>
        </svg>

        <svg
            v-else
            :viewBox="`0 0 ${width} ${HEIGHT}`"
            :width="width"
            :height="HEIGHT"
            class="block max-w-full"
            aria-hidden="true"
            @mouseleave="hovered = null"
        >
            <g v-for="tick in ticks" :key="tick.value">
                <line
                    :x1="LEFT"
                    :x2="width - RIGHT"
                    :y1="tick.y"
                    :y2="tick.y"
                    class="stroke-xq-border"
                />
                <text
                    :x="LEFT - 8"
                    :y="tick.y + 4"
                    text-anchor="end"
                    class="fill-xq-muted-foreground text-xs"
                >
                    {{ format(tick.value) }}
                </text>
            </g>
            <text
                v-for="item in xLabels"
                :key="item.index"
                :x="center(item.index)"
                :y="HEIGHT - 8"
                text-anchor="middle"
                class="fill-xq-muted-foreground text-xs"
            >
                {{ item.label }}
            </text>

            <template v-if="chart.type === 'bar'">
                <rect
                    v-for="bar in bars"
                    :key="bar.key"
                    :x="bar.x"
                    :y="bar.y"
                    :width="barWidth"
                    :height="bar.height"
                    rx="2"
                    :fill="color(bar.series)"
                    data-xq-chart-bar
                />
            </template>
            <template v-else>
                <line
                    v-if="hovered !== null"
                    :x1="center(hovered)"
                    :x2="center(hovered)"
                    :y1="TOP"
                    :y2="HEIGHT - BOTTOM"
                    class="stroke-xq-muted-foreground"
                    stroke-dasharray="3 3"
                />
                <polyline
                    v-for="(points, series) in lines"
                    :key="series"
                    :points="points"
                    fill="none"
                    :stroke="color(series)"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    data-xq-chart-line
                />
                <template v-if="hovered !== null">
                    <circle
                        v-for="(dataset, series) in chart.datasets"
                        :key="series"
                        :cx="center(hovered)"
                        :cy="y(dataset.data[hovered] ?? 0)"
                        r="4"
                        :fill="color(series)"
                        class="stroke-xq-card"
                        stroke-width="2"
                    />
                </template>
            </template>

            <rect
                v-for="(_, index) in chart.labels"
                :key="index"
                :x="LEFT + band * index"
                :y="TOP"
                :width="band"
                :height="plotHeight"
                fill="transparent"
                data-xq-chart-target
                @mouseenter="hovered = index"
            />
        </svg>

        <div
            v-if="tooltip"
            class="top-8 px-2.5 py-1.5 text-xs border-xq-border bg-xq-popover text-xq-popover-foreground rounded-xq-sm shadow-md pointer-events-none absolute z-10 -translate-x-1/2 border"
            :style="{ left: tooltip.left }"
            data-xq-chart-tooltip
        >
            <p v-if="tooltip.title" class="mb-1 font-medium">{{ tooltip.title }}</p>
            <p
                v-for="row in tooltip.rows"
                :key="row.series"
                class="gap-2 flex items-center whitespace-nowrap"
            >
                <span
                    aria-hidden="true"
                    class="size-2 rounded-sm"
                    :style="{ background: color(row.series) }"
                ></span>
                <span class="text-xq-muted-foreground">{{ row.label }}</span>
                <span class="font-medium ml-auto tabular-nums">{{ format(row.value ?? 0) }}</span>
            </p>
        </div>

        <table class="sr-only">
            <thead>
                <tr>
                    <th scope="col"></th>
                    <th v-for="dataset in chart.datasets" :key="dataset.label" scope="col">
                        {{ dataset.label }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(label, index) in chart.labels" :key="label">
                    <th scope="row">{{ label }}</th>
                    <td v-for="dataset in chart.datasets" :key="dataset.label">
                        {{ format(dataset.data[index] ?? 0) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
