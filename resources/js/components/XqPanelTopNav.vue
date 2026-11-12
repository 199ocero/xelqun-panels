<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { XqIcon, cn, XqMenuGlide } from '@xelqun/forms';
import { ChevronDown } from '@lucide/vue';
import {
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuTrigger,
} from 'reka-ui';
import { t } from '../composables/useMessages';
import { isOwnUrl, visitUrl } from '../lib/url';
import type { PanelNavigationGroup } from '../types';
import { menuClass, menuItemClass } from '../ui/classes';

defineProps<{ navigation: PanelNavigationGroup[] }>();

const itemClass =
    'gap-1.5 px-3 py-1.5 text-sm font-medium flex shrink-0 cursor-pointer items-center rounded-(--xq-pill-radius) whitespace-nowrap transition-colors hover:bg-xq-nav-hover';
</script>

<template>
    <nav
        :aria-label="t('Main')"
        class="gap-1 px-4 py-2 border-xq-border bg-xq-background lg:flex hidden [scrollbar-width:none] overflow-x-auto border-b"
        data-xq-top-nav
    >
        <template v-for="group in navigation" :key="group.label ?? ''">
            <template v-if="!group.label">
                <component
                    :is="isOwnUrl(item.url ?? '') ? Link : 'a'"
                    v-for="item in group.items"
                    :key="item.label"
                    :href="item.url ?? ''"
                    :aria-current="item.active ? 'page' : undefined"
                    :class="cn(itemClass, item.active && 'bg-xq-nav-active')"
                >
                    <XqIcon v-if="item.icon" :name="item.icon" class="size-4" aria-hidden="true" />
                    {{ item.label }}
                </component>
            </template>
            <DropdownMenuRoot v-else :modal="false">
                <DropdownMenuTrigger
                    :class="
                        cn(itemClass, group.items.some((item) => item.active) && 'bg-xq-nav-active')
                    "
                >
                    {{ group.label }}
                    <ChevronDown class="size-3.5" aria-hidden="true" />
                </DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent :class="menuClass" align="start" :side-offset="6">
                        <XqMenuGlide />
                        <DropdownMenuItem
                            v-for="item in group.items"
                            :key="item.label"
                            :class="menuItemClass"
                            @select="item.url && visitUrl(item.url)"
                        >
                            <XqIcon
                                v-if="item.icon"
                                :name="item.icon"
                                class="size-4 text-xq-muted-foreground"
                                aria-hidden="true"
                            />
                            {{ item.label }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
        </template>
    </nav>
</template>
