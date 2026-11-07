<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { XqIcon, cn } from '@xelqun/forms';
import { t } from '../composables/useMessages';
import type { PanelTab } from '../types';

defineProps<{ tabs: PanelTab[] }>();
</script>

<template>
    <nav
        :aria-label="t('Views')"
        class="gap-1 border-xq-border -mt-2 flex overflow-x-auto border-b"
        data-xq-tabs
    >
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.url ?? ''"
            :aria-current="tab.active ? 'page' : undefined"
            :class="
                cn(
                    'gap-1.5 px-3 py-2 text-sm font-medium flex shrink-0 items-center border-b-2 whitespace-nowrap',
                    tab.active
                        ? 'border-xq-primary text-xq-foreground'
                        : 'text-xq-muted-foreground hover:text-xq-foreground border-transparent',
                )
            "
        >
            <XqIcon v-if="tab.icon" :name="tab.icon" class="size-4" aria-hidden="true" />
            {{ tab.label }}
            <span
                v-if="tab.badge"
                class="px-1.5 text-xs bg-xq-muted text-xq-muted-foreground rounded-full"
            >
                {{ tab.badge }}
            </span>
        </Link>
    </nav>
</template>
