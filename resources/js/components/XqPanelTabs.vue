<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { XqIcon, XqTabGlide, cn } from '@xelqun/forms';
import { t } from '../composables/useMessages';
import type { PanelTab } from '../types';

defineProps<{ tabs: PanelTab[] }>();
</script>

<template>
    <nav
        :aria-label="t('Views')"
        class="gap-1 border-xq-border -mt-2 relative flex [scrollbar-width:none] overflow-x-auto border-b"
        data-xq-tabs
    >
        <XqTabGlide class="bottom-0 h-0.5 bg-xq-primary" />
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.url ?? ''"
            :aria-current="tab.active ? 'page' : undefined"
            :class="
                cn(
                    'gap-1.5 px-3 py-2 text-sm font-medium flex shrink-0 items-center whitespace-nowrap transition-colors duration-(--xq-duration-fast)',
                    tab.active
                        ? 'text-xq-foreground'
                        : 'text-xq-muted-foreground hover:text-xq-foreground',
                )
            "
        >
            <XqIcon v-if="tab.icon" :name="tab.icon" class="size-4" aria-hidden="true" />
            {{ tab.label }}
            <span
                v-if="tab.badge"
                class="px-1.5 text-xs bg-xq-secondary text-xq-secondary-foreground rounded-(--xq-pill-radius)"
            >
                {{ tab.badge }}
            </span>
        </Link>
    </nav>
</template>
