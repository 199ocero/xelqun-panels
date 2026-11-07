<script setup lang="ts">
import { XqIcon } from '@xelqun/forms';
import type { PanelStat } from '../../types';
import XqPanelNumber from './XqPanelNumber.vue';

defineProps<{ stats: PanelStat[] }>();

const WIDTH = 96;
const HEIGHT = 32;

/** Points for an SVG line. A flat series sits in the middle rather than dividing by zero. */
function points(values: number[]): string {
    const min = Math.min(...values);
    const range = Math.max(...values) - min;

    return values
        .map((value, index) => {
            const x = (index / Math.max(values.length - 1, 1)) * WIDTH;
            const y = range === 0 ? HEIGHT / 2 : HEIGHT - ((value - min) / range) * HEIGHT;

            return `${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');
}
</script>

<template>
    <div class="gap-4 sm:grid-cols-2 lg:grid-cols-4 grid" data-xq-stats>
        <article
            v-for="stat in stats"
            :key="stat.label"
            class="gap-3 p-5 border-xq-border bg-xq-card rounded-xq-lg relative grid border"
        >
            <div class="gap-3 flex items-center">
                <span
                    v-if="stat.icon"
                    class="size-9 bg-xq-muted rounded-lg flex shrink-0 items-center justify-center"
                >
                    <XqIcon :name="stat.icon" class="size-4.5" aria-hidden="true" />
                </span>
                <h3 class="text-sm text-xq-muted-foreground">{{ stat.label }}</h3>
            </div>
            <XqPanelNumber
                :value="stat.value"
                class="text-3xl font-semibold tracking-tight tabular-nums"
            />
            <p v-if="stat.description" class="text-sm text-xq-muted-foreground">
                {{ stat.description }}
            </p>
            <svg
                v-if="stat.chart.length > 1"
                :viewBox="`0 0 ${WIDTH} ${HEIGHT}`"
                :width="WIDTH"
                :height="HEIGHT"
                class="right-5 bottom-5 text-xq-primary absolute overflow-visible"
                aria-hidden="true"
                data-xq-sparkline
            >
                <polyline
                    :points="points(stat.chart)"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
        </article>
    </div>
</template>
