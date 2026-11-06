<script setup lang="ts">
import { useRegistry } from '@xelqun/forms';
import { XqTable } from '@xelqun/tables';
import type { TableSchema } from '@xelqun/tables';
import type { Component } from 'vue';
import type { PanelChart, PanelStat, PanelTile, PanelWidget } from '../../types';
import XqPanelChart from './XqPanelChart.vue';
import XqPanelListWidget from './XqPanelListWidget.vue';
import XqPanelStats from './XqPanelStats.vue';
import XqPanelTiles from './XqPanelTiles.vue';
import XqPanelWidgetCard from './XqPanelWidgetCard.vue';

defineProps<{ widgets: PanelWidget[] }>();

const registry = useRegistry();

/** An app's own widget, by the type it returns from getType(). */
function customWidget(type: string): Component | undefined {
    return registry?.get(type);
}
</script>

<template>
    <div class="gap-4 grid grid-cols-12" data-xq-widgets>
        <div
            v-for="widget in widgets"
            :key="widget.id"
            class="lg:[grid-column:span_var(--xq-widget-span)] min-w-0 col-span-12"
            :style="{ '--xq-widget-span': widget.columnSpan }"
            :data-xq-widget="widget.id"
        >
            <XqPanelStats
                v-if="widget.type === 'stats'"
                :stats="widget.data.stats as PanelStat[]"
            />
            <XqPanelWidgetCard
                v-else-if="widget.type === 'chart'"
                :heading="widget.data.heading"
                :see-all-url="widget.data.seeAllUrl"
            >
                <XqPanelChart :chart="widget.data.chart as PanelChart" class="px-5 pb-4" />
            </XqPanelWidgetCard>
            <XqPanelListWidget
                v-else-if="widget.type === 'list'"
                :table="widget.data.table as TableSchema"
                :heading="widget.data.heading"
                :see-all-url="widget.data.seeAllUrl"
            />
            <XqPanelTiles
                v-else-if="widget.type === 'tiles'"
                :tiles="widget.data.tiles as PanelTile[]"
                :heading="widget.data.heading"
                :see-all-url="widget.data.seeAllUrl"
            />
            <XqPanelWidgetCard
                v-else-if="widget.type === 'table'"
                :heading="widget.data.heading"
                :see-all-url="widget.data.seeAllUrl"
            >
                <XqTable :table="widget.data.table as TableSchema" class="p-4" />
            </XqPanelWidgetCard>
            <component :is="customWidget(widget.type)" v-else v-bind="widget.data" />
        </div>
    </div>
</template>
