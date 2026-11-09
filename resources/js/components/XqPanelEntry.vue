<script setup lang="ts">
import { XqIcon, cn } from '@xelqun/forms';
import type { SchemaNode } from '@xelqun/forms';
import type { TableCell, TableColumn } from '@xelqun/tables';
import { Check, Copy } from '@lucide/vue';
import { computed, ref } from 'vue';
import { t } from '../composables/useMessages';
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
const options = computed(() => column.value.props ?? {});
const copied = ref(false);

async function copy(): Promise<void> {
    try {
        await navigator.clipboard.writeText(values.value.join(', '));
        copied.value = true;
        setTimeout(() => (copied.value = false), options.value.copyMessageDuration ?? 2000);
    } catch {
        // Nothing copied, and the button says so by not changing.
    }
}

const clampStyle = computed(() =>
    options.value.lineClamp
        ? {
              display: '-webkit-box',
              WebkitLineClamp: options.value.lineClamp,
              WebkitBoxOrient: 'vertical' as const,
              overflow: 'hidden',
          }
        : undefined,
);
</script>

<template>
    <div
        :class="
            cn(
                'min-w-0',
                node.props?.inlineLabel && 'gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] grid',
            )
        "
        :title="cell.tooltip"
        data-xq-entry
    >
        <p class="mb-1 gap-2 text-sm text-xq-muted-foreground flex items-center">
            {{ column.label }}
            <span v-if="node.props?.hint" class="text-xs ms-auto" data-xq-entry-hint>
                {{ node.props.hint }}
            </span>
        </p>
        <div class="min-w-0">
            <p
                v-if="cell.description && options.descriptionAbove"
                class="mb-0.5 text-xs text-xq-muted-foreground"
            >
                {{ cell.description }}
            </p>
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
                <div
                    v-for="pair in pairs"
                    :key="pair.key"
                    class="gap-3 px-3 py-1.5 grid grid-cols-2"
                >
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
            <!-- Cleaned on the server, as a rich editor's content is. -->
            <div
                v-else-if="node.props?.html"
                class="xq-rich-content text-sm"
                data-xq-entry-html
                v-html="node.props.html"
            ></div>
            <span v-else-if="values.length > 0" class="gap-1.5 inline-flex items-start">
                <component
                    :is="cell.url ? 'a' : 'p'"
                    :href="cell.url"
                    :class="
                        cn(
                            'gap-1.5 text-sm inline-flex items-center break-words',
                            cell.color && textToneClasses[cell.color],
                            cell.url && 'hover:underline',
                        )
                    "
                    :style="clampStyle"
                >
                    <XqIcon v-if="cell.icon" :name="cell.icon" class="size-4" aria-hidden="true" />
                    {{ node.props?.prefix }}{{ values.join(', ') }}{{ node.props?.suffix }}
                </component>
                <button
                    v-if="options.copyable"
                    type="button"
                    class="size-5 rounded-xq-sm text-xq-muted-foreground hover:bg-xq-accent flex shrink-0 cursor-pointer items-center justify-center"
                    :aria-label="copied ? (options.copyMessage ?? t('Copied')) : t('Copy')"
                    data-xq-entry-copy
                    @click="copy"
                >
                    <Check v-if="copied" class="size-3.5 text-xq-success" aria-hidden="true" />
                    <Copy v-else class="size-3.5" aria-hidden="true" />
                </button>
            </span>
            <p v-else class="text-sm text-xq-muted-foreground" data-xq-entry-placeholder>
                {{ options.placeholder ?? '—' }}
            </p>
            <p
                v-if="cell.description && !options.descriptionAbove"
                class="mt-0.5 text-xs text-xq-muted-foreground"
            >
                {{ cell.description }}
            </p>
        </div>
    </div>
</template>
