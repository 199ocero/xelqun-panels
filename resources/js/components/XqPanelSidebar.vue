<script setup lang="ts">
import { t } from '../composables/useMessages';
import { Link } from '@inertiajs/vue3';
import { ChevronDown, PanelLeft, Search } from '@lucide/vue';
import { XqIcon, cn } from '@xelqun/forms';
import { TooltipProvider } from 'reka-ui';
import { computed, onMounted, ref } from 'vue';
import { useActivePill, useGlidingHover } from '../composables/useGlidingHover';
import type { PanelNavigationGroup, PanelNavigationItem, PanelShell } from '../types';
import { iconButtonClass } from '../ui/classes';
import XqPanelHook from './XqPanelHook.vue';
import XqPanelNavItem from './XqPanelNavItem.vue';
import XqPanelNewMenu from './XqPanelNewMenu.vue';
import XqPanelSidebarCard from './XqPanelSidebarCard.vue';
import XqPanelTenantMenu from './XqPanelTenantMenu.vue';
import XqPanelUserMenu from './XqPanelUserMenu.vue';

const props = defineProps<{ panel: PanelShell; collapsed?: boolean }>();
defineEmits<{ collapse: []; search: [] }>();

const list = ref<HTMLElement>();
const { glideClass, glideStyle, glideTo, hideGlide } = useGlidingHover();
const { activeClass, activeStyle } = useActivePill(list, () => [
    props.panel.navigation,
    props.collapsed,
]);

const foldedKey = computed(() => `xq-panel-nav-folded:${props.panel.id}`);
/** Groups the viewer folded, or that start folded until they open them. */
const folded = ref<Record<string, boolean>>({});

onMounted(() => {
    try {
        folded.value = JSON.parse(localStorage.getItem(foldedKey.value) ?? '{}');
    } catch {
        // Nothing remembered.
    }
});

function isFolded(group: PanelNavigationGroup): boolean {
    return !!group.collapsible && (folded.value[group.label ?? ''] ?? !!group.collapsed);
}

function toggleGroup(group: PanelNavigationGroup): void {
    folded.value = { ...folded.value, [group.label ?? '']: !isFolded(group) };

    try {
        localStorage.setItem(foldedKey.value, JSON.stringify(folded.value));
    } catch {
        // Not remembered this time.
    }
}

function showsChildren(item: PanelNavigationItem): boolean {
    return !!item.children?.length && (item.active || item.children.some((child) => child.active));
}

const brandInitial = computed(() => props.panel.brand.name.charAt(0).toUpperCase());
</script>

<template>
    <TooltipProvider :delay-duration="150">
        <nav :aria-label="t('Main')" class="gap-4 p-3 min-h-0 flex h-full flex-col" data-xq-sidebar>
            <div :class="['gap-2.5 h-9 flex items-center', collapsed ? 'justify-center' : 'px-1']">
                <Link
                    v-if="!collapsed"
                    :href="panel.url"
                    class="gap-2.5 font-semibold min-w-0 flex flex-1 items-center"
                >
                    <template v-if="panel.brand.logo">
                        <img
                            :src="panel.brand.logo"
                            :alt="panel.brand.name"
                            :style="{ height: panel.brand.logoHeight }"
                            :data-xq-logo="panel.brand.darkLogo ? 'light' : undefined"
                        />
                        <img
                            v-if="panel.brand.darkLogo"
                            :src="panel.brand.darkLogo"
                            :alt="panel.brand.name"
                            :style="{ height: panel.brand.logoHeight }"
                            data-xq-logo="dark"
                        />
                    </template>
                    <template v-else>
                        <span
                            aria-hidden="true"
                            class="text-xs size-6.5 rounded-lg bg-xq-foreground text-xq-background flex shrink-0 items-center justify-center"
                        >
                            {{ brandInitial }}
                        </span>
                        <span class="truncate">{{ panel.brand.name }}</span>
                    </template>
                </Link>
                <button
                    type="button"
                    :class="iconButtonClass"
                    :aria-label="collapsed ? t('Expand sidebar') : t('Collapse sidebar')"
                    :aria-expanded="!collapsed"
                    @click="$emit('collapse')"
                >
                    <PanelLeft
                        :class="[
                            'size-4 transition-[rotate] duration-(--xq-duration-slow) ease-(--xq-ease-out)',
                            collapsed && 'rotate-180',
                        ]"
                        aria-hidden="true"
                    />
                </button>
            </div>

            <XqPanelHook name="sidebar.start" />

            <XqPanelTenantMenu v-if="panel.tenant" :tenant="panel.tenant" :collapsed="collapsed" />

            <div
                v-if="(collapsed && panel.globalSearch) || panel.quickCreate.length > 0"
                :class="['gap-2 flex items-center', collapsed ? 'flex-col' : 'px-1']"
            >
                <XqPanelNewMenu
                    v-if="panel.quickCreate.length > 0"
                    :items="panel.quickCreate"
                    :collapsed="collapsed"
                />
                <button
                    v-if="panel.globalSearch"
                    type="button"
                    :class="iconButtonClass"
                    :aria-label="t('Search')"
                    @click="$emit('search')"
                >
                    <Search class="size-4" aria-hidden="true" />
                </button>
            </div>

            <div
                ref="list"
                :class="[
                    'min-h-0 relative flex flex-1 flex-col overflow-y-auto',
                    collapsed ? 'gap-2' : 'gap-5',
                ]"
                @mouseover="glideTo"
                @focusin="glideTo"
                @mouseleave="hideGlide"
                @focusout="hideGlide"
            >
                <span
                    aria-hidden="true"
                    :class="activeClass"
                    :style="activeStyle"
                    data-xq-nav-active
                ></span>
                <span
                    aria-hidden="true"
                    :class="glideClass"
                    :style="glideStyle"
                    data-xq-nav-glide
                ></span>
                <div
                    v-for="(group, index) in panel.navigation"
                    :key="group.label ?? ''"
                    role="group"
                    :aria-label="group.label ?? undefined"
                    class="gap-0.5 relative grid"
                >
                    <span
                        v-if="collapsed && index > 0"
                        aria-hidden="true"
                        class="mx-2 mb-2 bg-xq-border block h-px"
                        data-xq-nav-divider
                    ></span>
                    <button
                        v-if="group.label && !collapsed && group.collapsible"
                        type="button"
                        class="gap-1.5 px-2.5 pb-1 text-xs font-medium text-xq-muted-foreground hover:text-xq-foreground flex w-full cursor-pointer items-center"
                        :aria-expanded="!isFolded(group)"
                        data-xq-nav-group-toggle
                        @click="toggleGroup(group)"
                    >
                        <XqIcon v-if="group.icon" :name="group.icon" class="size-3.5" />
                        {{ group.label }}
                        <ChevronDown
                            :class="
                                cn(
                                    'size-3.5 ml-auto transition-transform duration-(--xq-duration-base)',
                                    isFolded(group) && '-rotate-90',
                                )
                            "
                            aria-hidden="true"
                        />
                    </button>
                    <p
                        v-else-if="group.label && !collapsed"
                        aria-hidden="true"
                        class="gap-1.5 px-2.5 pb-1 text-xs font-medium text-xq-muted-foreground flex items-center"
                    >
                        <XqIcon v-if="group.icon" :name="group.icon" class="size-3.5" />
                        {{ group.label }}
                    </p>
                    <template v-if="collapsed || !isFolded(group)">
                        <template v-for="item in group.items" :key="item.label">
                            <XqPanelNavItem :item="item" :collapsed="collapsed" />
                            <template v-if="showsChildren(item) && !collapsed">
                                <XqPanelNavItem
                                    v-for="child in item.children"
                                    :key="child.label"
                                    :item="child"
                                    nested
                                />
                            </template>
                        </template>
                    </template>
                </div>
            </div>

            <XqPanelHook name="sidebar.end" />

            <XqPanelSidebarCard
                v-if="panel.sidebarFooter && !collapsed"
                :card="panel.sidebarFooter"
            />

            <XqPanelUserMenu
                v-if="panel.user"
                :user="panel.user"
                :logout-url="panel.logoutUrl"
                :profile-url="panel.profileUrl"
                :items="panel.userMenuItems"
                :can-change-theme="panel.theme.forced === null"
                :collapsed="collapsed"
            />
        </nav>
    </TooltipProvider>
</template>
