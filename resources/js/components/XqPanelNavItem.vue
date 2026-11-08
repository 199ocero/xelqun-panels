<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { XqIcon, cn } from '@xelqun/forms';
import { TooltipContent, TooltipPortal, TooltipRoot, TooltipTrigger } from 'reka-ui';
import { isOwnUrl } from '../lib/url';
import type { PanelNavigationItem } from '../types';
import { sidebarTooltipClass } from '../ui/classes';

defineProps<{ item: PanelNavigationItem; collapsed?: boolean }>();
</script>

<template>
    <TooltipRoot v-if="item.url" :disabled="!collapsed">
        <TooltipTrigger as-child>
            <component
                :is="isOwnUrl(item.url) ? Link : 'a'"
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
                <!-- An item with no icon keeps its place, so labels line up, and shows its initial when collapsed. -->
                <span
                    v-else
                    class="size-4.5 text-xs font-medium text-xq-muted-foreground flex shrink-0 items-center justify-center"
                    aria-hidden="true"
                    data-xq-nav-placeholder
                >
                    {{ collapsed ? item.label.charAt(0).toUpperCase() : '' }}
                </span>
                <span :class="collapsed ? 'sr-only' : 'truncate'">{{ item.label }}</span>
                <span
                    v-if="item.badge && !collapsed"
                    :key="item.badge"
                    class="text-xs text-xq-muted-foreground ml-auto animate-xq-badge-pop tabular-nums"
                >
                    {{ item.badge }}
                </span>
            </component>
        </TooltipTrigger>
        <TooltipPortal>
            <TooltipContent
                side="right"
                :side-offset="14"
                :class="sidebarTooltipClass"
                data-xq-nav-tooltip
            >
                {{ item.label }}
                <span v-if="item.badge" class="tabular-nums opacity-70">{{ item.badge }}</span>
            </TooltipContent>
        </TooltipPortal>
    </TooltipRoot>
</template>
