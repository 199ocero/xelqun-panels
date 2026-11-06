<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { XqAction, XqActionGroup, XqIcon, cn } from '@xelqun/forms';
import type { ActionRunner } from '@xelqun/forms';
import { actionNode, createTableStore } from '@xelqun/tables';
import type { TableSchema } from '@xelqun/tables';
import { ChevronRight } from 'lucide-vue-next';
import { computed, onScopeDispose, watch } from 'vue';
import { toneClasses } from './tones';
import XqPanelWidgetCard from './XqPanelWidgetCard.vue';

const props = defineProps<{
    table: TableSchema;
    heading: string | null;
    seeAllUrl: string | null;
}>();

const store = createTableStore(props.table);
const { schema } = store;

const rows = computed(() =>
    schema.value.rows.map((row) => ({
        ...row,
        title: row.cells.title.state as string,
        meta: row.cells.meta.state as string | null,
        icon: row.cells.icon.state as string | null,
        iconColor: row.cells.iconColor.state as string,
        progress: row.cells.progress.state as number | null,
        progressNote: row.cells.progressNote.state as string | null,
    })),
);

/**
 * An action here can change what the rest of the dashboard says, so the numbers
 * and the heading come along too.
 */
function rowRunner(record: string): ActionRunner {
    const runner = store.actionRunner({ kind: 'record', record });

    return {
        ...runner,
        async runAction(node, modal) {
            const result = await runner.runAction(node, modal);

            if (result && !result.redirect) {
                router.reload({ only: ['widgets', 'page'] });
            }

            return result;
        },
    };
}

// A token is new on every render, so this only runs when the page reloads.
watch(
    () => props.table.token,
    () => store.reset(props.table),
);

onScopeDispose(store.dispose);
</script>

<template>
    <XqPanelWidgetCard :heading="heading" :count="schema.pagination.total" :see-all-url="seeAllUrl">
        <ul class="divide-xq-border divide-y">
            <li
                v-for="row in rows"
                :key="row.key"
                class="gap-3 px-5 py-3 relative flex items-center"
                data-xq-list-row
            >
                <span
                    v-if="row.icon"
                    :class="
                        cn(
                            'size-8 rounded-lg flex shrink-0 items-center justify-center',
                            toneClasses[row.iconColor] ?? toneClasses.gray,
                        )
                    "
                >
                    <XqIcon :name="row.icon" class="size-4" aria-hidden="true" />
                </span>
                <div class="min-w-0 flex-1">
                    <component
                        :is="row.url ? Link : 'p'"
                        :href="row.url"
                        class="text-sm font-medium block truncate"
                    >
                        <span v-if="row.url" class="inset-0 absolute" aria-hidden="true"></span>
                        {{ row.title }}
                    </component>
                    <p v-if="row.meta" class="text-xs text-xq-muted-foreground truncate">
                        {{ row.meta }}
                    </p>
                </div>
                <div v-if="row.progress !== null" class="w-32 sm:w-40 shrink-0">
                    <div
                        role="progressbar"
                        :aria-label="row.title"
                        :aria-valuenow="Math.round(row.progress * 100)"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        class="bg-xq-muted h-1.5 overflow-hidden rounded-full"
                    >
                        <div
                            class="bg-xq-primary h-full rounded-full"
                            :style="{ width: `${row.progress * 100}%` }"
                        ></div>
                    </div>
                    <p
                        v-if="row.progressNote"
                        class="mt-1 text-xs text-xq-muted-foreground text-right"
                    >
                        {{ row.progressNote }}
                    </p>
                </div>
                <div v-if="row.actions.length > 0" class="gap-2 relative flex shrink-0">
                    <component
                        :is="action.actions ? XqActionGroup : XqAction"
                        v-for="action in row.actions"
                        :key="action.name"
                        :node="actionNode(action)"
                        :runner="rowRunner(row.key)"
                    />
                </div>
                <ChevronRight
                    v-else-if="row.url && row.progress === null"
                    class="size-4 text-xq-muted-foreground shrink-0"
                    aria-hidden="true"
                />
            </li>
            <li
                v-if="rows.length === 0"
                class="px-5 py-8 text-sm text-xq-muted-foreground text-center"
            >
                {{ schema.emptyState?.heading ?? 'Nothing here.' }}
            </li>
        </ul>
    </XqPanelWidgetCard>
</template>
