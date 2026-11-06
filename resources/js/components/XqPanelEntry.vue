<script setup lang="ts">
import { XqIcon, cn } from '@xelqun/forms';
import type { SchemaNode } from '@xelqun/forms';
import type { TableCell, TableColumn } from '@xelqun/tables';
import { computed } from 'vue';
import { textToneClasses, toneClasses } from './widgets/tones';

const props = defineProps<{ node: SchemaNode }>();

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
        <img
            v-if="column.type === 'image-column' && cell.state"
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
