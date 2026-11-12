<script setup lang="ts">
import { t } from '../composables/useMessages';
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Menu, Search } from '@lucide/vue';
import type { PanelBreadcrumb } from '../types';
import { iconButtonClass } from '../ui/classes';
import XqPanelHook from './XqPanelHook.vue';

defineProps<{ breadcrumbs: PanelBreadcrumb[]; searchable?: boolean }>();
defineEmits<{ menu: []; search: [] }>();

const shortcut =
    typeof navigator !== 'undefined' && /mac/i.test(navigator.platform) ? '⌘K' : 'Ctrl K';
</script>

<template>
    <header
        class="gap-3 px-4 h-14 border-xq-border bg-xq-background md:grid md:grid-cols-[1fr_auto_1fr] flex shrink-0 items-center justify-between border-b"
        data-xq-topbar
    >
        <div class="gap-2 min-w-0 flex items-center">
            <button
                type="button"
                :class="[iconButtonClass, 'lg:hidden']"
                :aria-label="t('Open navigation')"
                @click="$emit('menu')"
            >
                <Menu class="size-4.5" aria-hidden="true" />
            </button>
            <XqPanelHook name="topbar.start" />
            <slot name="start" />
            <nav :aria-label="t('Breadcrumb')" class="min-w-0">
                <ol class="gap-1.5 text-sm flex items-center">
                    <li
                        v-for="(crumb, index) in breadcrumbs"
                        :key="crumb.label"
                        class="gap-1.5 min-w-0 flex items-center"
                    >
                        <ChevronRight
                            v-if="index > 0"
                            class="size-3.5 text-xq-muted-foreground shrink-0"
                            aria-hidden="true"
                        />
                        <Link
                            v-if="crumb.url"
                            :href="crumb.url"
                            class="text-xq-muted-foreground hover:text-xq-foreground truncate"
                        >
                            {{ crumb.label }}
                        </Link>
                        <span
                            v-else
                            class="font-medium animate-xq-fade-in truncate"
                            aria-current="page"
                        >
                            {{ crumb.label }}
                        </span>
                    </li>
                </ol>
            </nav>
        </div>

        <button
            v-if="searchable"
            type="button"
            class="gap-2 px-3 text-sm h-9 w-72 bg-xq-muted text-xq-muted-foreground hover:bg-xq-accent focus-visible:ring-xq-ring md:flex hidden cursor-pointer items-center rounded-(--xq-pill-radius) transition-[background-color,scale] duration-(--xq-duration-fast) focus-visible:ring-2 focus-visible:outline-none active:scale-[0.98]"
            data-xq-search-trigger
            @click="$emit('search')"
        >
            <Search class="size-4" aria-hidden="true" />
            {{ t('Search') }}
            <kbd class="text-xs font-sans ml-auto">{{ shortcut }}</kbd>
        </button>
        <div class="flex items-center justify-end">
            <button
                v-if="searchable"
                type="button"
                :class="[iconButtonClass, 'md:hidden']"
                :aria-label="t('Search')"
                @click="$emit('search')"
            >
                <Search class="size-4" aria-hidden="true" />
            </button>
            <XqPanelHook name="topbar.end" />
            <slot />
        </div>
    </header>
</template>
