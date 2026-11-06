<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Menu, Search } from 'lucide-vue-next';
import type { PanelBreadcrumb } from '../types';
import { iconButtonClass } from '../ui/classes';

defineProps<{ breadcrumbs: PanelBreadcrumb[] }>();
defineEmits<{ menu: []; search: [] }>();

const shortcut =
    typeof navigator !== 'undefined' && /mac/i.test(navigator.platform) ? '⌘K' : 'Ctrl K';
</script>

<template>
    <header
        class="gap-3 px-4 h-14 border-xq-border bg-xq-card md:grid md:grid-cols-[1fr_auto_1fr] flex shrink-0 items-center justify-between border-b"
        data-xq-topbar
    >
        <div class="gap-2 min-w-0 flex items-center">
            <button
                type="button"
                :class="[iconButtonClass, 'lg:hidden']"
                aria-label="Open navigation"
                @click="$emit('menu')"
            >
                <Menu class="size-4.5" aria-hidden="true" />
            </button>
            <nav aria-label="Breadcrumb" class="min-w-0">
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
                        <span v-else class="font-medium truncate" aria-current="page">
                            {{ crumb.label }}
                        </span>
                    </li>
                </ol>
            </nav>
        </div>

        <button
            type="button"
            class="gap-2 px-3 text-sm h-9 w-72 bg-xq-muted text-xq-muted-foreground hover:bg-xq-accent focus-visible:ring-xq-ring md:flex hidden cursor-pointer items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:outline-none"
            data-xq-search-trigger
            @click="$emit('search')"
        >
            <Search class="size-4" aria-hidden="true" />
            Search
            <kbd class="text-xs font-sans ml-auto">{{ shortcut }}</kbd>
        </button>
        <div class="flex items-center justify-end">
            <button
                type="button"
                :class="[iconButtonClass, 'md:hidden']"
                aria-label="Search"
                @click="$emit('search')"
            >
                <Search class="size-4" aria-hidden="true" />
            </button>
            <slot />
        </div>
    </header>
</template>
