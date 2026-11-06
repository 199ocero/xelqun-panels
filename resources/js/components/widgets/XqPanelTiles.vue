<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { XqIcon, cn } from '@xelqun/forms';
import type { PanelTile } from '../../types';
import { toneClasses } from './tones';
import XqPanelWidgetCard from './XqPanelWidgetCard.vue';

defineProps<{ tiles: PanelTile[]; heading: string | null; seeAllUrl: string | null }>();
</script>

<template>
    <XqPanelWidgetCard :heading="heading" :count="tiles.length" :see-all-url="seeAllUrl">
        <div class="gap-3 px-5 pb-5 pt-2 sm:grid-cols-3 grid grid-cols-2" data-xq-tiles>
            <component
                :is="tile.url ? Link : 'div'"
                v-for="tile in tiles"
                :key="tile.label"
                :href="tile.url ?? undefined"
                :class="
                    cn(
                        'gap-2 p-3 bg-xq-muted rounded-xq-md grid',
                        tile.url && 'hover:bg-xq-accent transition-colors',
                    )
                "
            >
                <XqIcon
                    v-if="tile.icon"
                    :name="tile.icon"
                    class="size-5 text-xq-muted-foreground"
                    aria-hidden="true"
                />
                <p class="text-sm font-medium truncate">{{ tile.label }}</p>
                <span
                    v-if="tile.status"
                    :class="
                        cn(
                            'gap-1.5 px-2 text-xs py-0.5 inline-flex w-fit items-center rounded-full',
                            toneClasses[tile.color] ?? toneClasses.gray,
                        )
                    "
                >
                    <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
                    {{ tile.status }}
                </span>
            </component>
        </div>
    </XqPanelWidgetCard>
</template>
