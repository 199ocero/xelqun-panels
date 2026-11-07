<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { XqIcon, cn } from '@xelqun/forms';
import type { PanelNavigationItem } from '../types';

defineProps<{ item: PanelNavigationItem; collapsed?: boolean }>();
</script>

<template>
    <Link
        v-if="item.url"
        :href="item.url"
        :aria-current="item.active ? 'page' : undefined"
        :title="collapsed ? item.label : undefined"
        data-xq-nav-item
        :class="
            cn(
                'gap-2.5 px-2.5 text-sm h-9 rounded-xq-sm relative flex items-center transition-colors',
                'focus-visible:ring-xq-ring focus-visible:ring-2 focus-visible:outline-none',
                item.active
                    ? 'font-medium text-xq-foreground bg-xq-nav-active'
                    : 'text-xq-foreground/80',
                collapsed && 'px-0 justify-center',
            )
        "
    >
        <XqIcon
            v-if="item.icon"
            :name="item.icon"
            class="size-4.5 text-xq-muted-foreground shrink-0"
            aria-hidden="true"
        />
        <span :class="collapsed ? 'sr-only' : 'truncate'">{{ item.label }}</span>
        <span
            v-if="item.badge && !collapsed"
            class="text-xs text-xq-muted-foreground ml-auto tabular-nums"
        >
            {{ item.badge }}
        </span>
    </Link>
</template>
