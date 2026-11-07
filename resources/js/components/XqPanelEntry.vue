<script setup lang="ts">
import { XqIcon, cn } from '@xelqun/forms';
import type { SchemaNode } from '@xelqun/forms';
import type { TableCell, TableColumn } from '@xelqun/tables';
import { computed } from 'vue';
import { textToneClasses, toneClasses } from './widgets/tones';

const props = defineProps<{ node: SchemaNode }>();

const display = computed(() => props.node.props?.display as string);
const pairs = computed(() => props.node.props?.pairs as Array<{ key: string; value: string }>);
const rows = computed(() => props.node.props?.rows as Array<Array<Record<string, unknown>>>);
const entryNode = (entry: Record<string, unknown>): SchemaNode =>
    ({ ...props.node, props: entry }) as SchemaNode;

const column = computed(() => props.node.props?.column as TableColumn);
const cell = computed(() => props.node.props?.cell as TableCell);
const values = computed(() =>
    [cell.value.state].flat().filter((value) => value !== null && value !== undefined),
);
const tone = computed(() => cell.value.color ?? 'gray');
</script>

<template>
    <div class="min-w-0" data-xq-entry>
        <p class="mb-1 text-sm text-xq-muted-foreground">{{ column.label }}</p>
        <span
            v-if="display === 'color' && cell.state"
            class="gap-2 text-sm inline-flex items-center"
        >
            <span
                aria-hidden="true"
                class="size-5 border-xq-border rounded-xq-sm border"
                :style="{ backgroundColor: String(cell.state) }"
            ></span>
            {{ cell.state }}
        </span>
        <pre
            v-else-if="display === 'code'"
            class="p-3 text-xs bg-xq-muted rounded-xq-sm font-mono overflow-x-auto"
        ><code>{{ node.props?.text }}</code></pre>
        <dl
            v-else-if="display === 'key-value'"
            class="border-xq-border rounded-xq-sm divide-xq-border text-sm divide-y border"
        >
            <div v-for="pair in pairs" :key="pair.key" class="gap-3 px-3 py-1.5 grid grid-cols-2">
                <dt class="text-xq-muted-foreground">{{ pair.key }}</dt>
                <dd class="min-w-0 break-words">{{ pair.value }}</dd>
            </div>
        </dl>
        <div v-else-if="display === 'repeatable'" class="gap-3 grid">
            <div
                v-for="(row, index) in rows"
                :key="index"
                class="gap-3 p-3 border-xq-border rounded-xq-sm sm:grid-cols-2 grid border"
            >
                <XqPanelEntry
                    v-for="(entry, position) in row"
                    :key="position"
                    :node="entryNode(entry)"
                />
            </div>
        </div>
        <img
            v-else-if="column.type === 'image-column' && cell.state"
            :src="String(cell.state)"
            :alt="cell.alt ?? column.label"
            :width="column.props.size"
            :height="column.props.size"
            :class="['object-cover', column.props.circular ? 'rounded-full' : 'rounded-xq-sm']"
        />
        <span
            v-else-if="column.type === 'icon-column' && cell.icon"
            :title="cell.label"
            :class="cn('inline-flex', textToneClasses[tone])"
        >
            <XqIcon :name="cell.icon" class="size-5" aria-hidden="true" />
            <span v-if="cell.label" class="sr-only">{{ cell.label }}</span>
        </span>
        <div v-else-if="column.props.badge && values.length > 0" class="gap-1 flex flex-wrap">
            <span
                v-for="(value, index) in values"
                :key="index"
                :class="cn('px-2 py-0.5 text-xs font-medium rounded-full', toneClasses[tone])"
            >
                {{ value }}
            </span>
        </div>
        <component
            :is="cell.url ? 'a' : 'p'"
            v-else-if="values.length > 0"
            :href="cell.url"
            :class="
                cn(
                    'gap-1.5 text-sm inline-flex items-center break-words',
                    cell.color && textToneClasses[cell.color],
                    cell.url && 'hover:underline',
                )
            "
        >
            <XqIcon v-if="cell.icon" :name="cell.icon" class="size-4" aria-hidden="true" />
            {{ values.join(', ') }}
        </component>
        <p v-else class="text-sm text-xq-muted-foreground">—</p>
    </div>
</template>
