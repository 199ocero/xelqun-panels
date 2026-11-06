<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { PanelLeft, Search } from 'lucide-vue-next';
import { computed } from 'vue';
import type { PanelShell } from '../types';
import { iconButtonClass } from '../ui/classes';
import XqPanelNavItem from './XqPanelNavItem.vue';
import XqPanelSidebarCard from './XqPanelSidebarCard.vue';
import XqPanelUserMenu from './XqPanelUserMenu.vue';

const props = defineProps<{ panel: PanelShell; collapsed?: boolean }>();
defineEmits<{ collapse: []; search: [] }>();

const brandInitial = computed(() => props.panel.brand.name.charAt(0).toUpperCase());
</script>

<template>
    <nav aria-label="Main" class="gap-4 p-3 min-h-0 flex h-full flex-col" data-xq-sidebar>
        <div :class="['gap-2.5 h-9 flex items-center', collapsed ? 'justify-center' : 'px-1']">
            <Link
                v-if="!collapsed"
                :href="panel.url"
                class="gap-2.5 font-semibold min-w-0 flex flex-1 items-center"
            >
                <span
                    aria-hidden="true"
                    class="text-xs size-6.5 rounded-lg bg-xq-foreground text-xq-background flex shrink-0 items-center justify-center"
                >
                    {{ brandInitial }}
                </span>
                <span class="truncate">{{ panel.brand.name }}</span>
            </Link>
            <button
                type="button"
                :class="iconButtonClass"
                :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                :aria-expanded="!collapsed"
                @click="$emit('collapse')"
            >
                <PanelLeft class="size-4" aria-hidden="true" />
            </button>
        </div>

        <button
            v-if="collapsed"
            type="button"
            :class="[iconButtonClass, 'mx-auto']"
            aria-label="Search"
            @click="$emit('search')"
        >
            <Search class="size-4" aria-hidden="true" />
        </button>

        <div class="gap-5 min-h-0 flex flex-1 flex-col overflow-y-auto">
            <div
                v-for="group in panel.navigation"
                :key="group.label ?? ''"
                role="group"
                :aria-label="group.label ?? undefined"
                class="gap-0.5 grid"
            >
                <p
                    v-if="group.label && !collapsed"
                    aria-hidden="true"
                    class="px-2.5 pb-1 text-xs font-medium text-xq-muted-foreground"
                >
                    {{ group.label }}
                </p>
                <XqPanelNavItem
                    v-for="item in group.items"
                    :key="item.label"
                    :item="item"
                    :collapsed="collapsed"
                />
            </div>
        </div>

        <XqPanelSidebarCard v-if="panel.sidebarFooter && !collapsed" :card="panel.sidebarFooter" />

        <XqPanelUserMenu
            v-if="panel.user"
            :user="panel.user"
            :logout-url="panel.logoutUrl"
            :profile-url="panel.profileUrl"
            :collapsed="collapsed"
        />
    </nav>
</template>
