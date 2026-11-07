<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { XqIcon, cn } from '@xelqun/forms';
import { TooltipContent, TooltipPortal, TooltipRoot, TooltipTrigger } from 'reka-ui';
import type { PanelNavigationItem } from '../types';

defineProps<{ item: PanelNavigationItem; collapsed?: boolean }>();
</script>

<template>
    <TooltipRoot v-if="item.url" :disabled="!collapsed">
        <TooltipTrigger as-child>
            <Link
                :href="item.url"
                :aria-current="item.active ? 'page' : undefined"
                data-xq-nav-item
                :class="
                    cn(
                        'group gap-2.5 px-2.5 text-sm h-9 rounded-xq-sm relative flex items-center transition-[color,scale] duration-(--xq-duration-fast) active:scale-[0.98]',
                        'focus-visible:ring-xq-ring focus-visible:ring-2 focus-visible:outline-none',
                        item.active ? 'font-medium text-xq-foreground' : 'text-xq-foreground/80',
                        collapsed && 'px-0 justify-center',
                    )
                "
            >
                <XqIcon
                    v-if="item.icon"
                    :name="item.icon"
                    class="size-4.5 text-xq-muted-foreground group-hover:text-xq-foreground shrink-0 transition-[scale,color] duration-(--xq-duration-base) ease-(--xq-ease-spring) group-hover:scale-110"
                    aria-hidden="true"
                />
                <span :class="collapsed ? 'sr-only' : 'truncate'">{{ item.label }}</span>
                <span
                    v-if="item.badge && !collapsed"
                    :key="item.badge"
                    class="text-xs text-xq-muted-foreground ml-auto animate-xq-badge-pop tabular-nums"
                >
                    {{ item.badge }}
                </span>
            </Link>
        </TooltipTrigger>
        <TooltipPortal>
            <TooltipContent
                side="right"
                :side-offset="14"
                class="gap-2 px-2 py-1 text-xs font-medium shadow-md rounded-xq-sm bg-xq-foreground text-xq-background data-[state=closed]:animate-xq-pop-out data-[state=delayed-open]:animate-xq-pop-in data-[state=instant-open]:animate-xq-pop-in z-50 flex origin-(--reka-tooltip-content-transform-origin) items-center"
                data-xq-nav-tooltip
            >
                {{ item.label }}
                <span v-if="item.badge" class="tabular-nums opacity-70">{{ item.badge }}</span>
            </TooltipContent>
        </TooltipPortal>
    </TooltipRoot>
</template>
