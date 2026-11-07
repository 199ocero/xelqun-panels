<script setup lang="ts">
import { t } from '../composables/useMessages';
import { Link } from '@inertiajs/vue3';
import { PanelLeft, Search } from 'lucide-vue-next';
import { computed } from 'vue';
import { useGlidingHover } from '../composables/useGlidingHover';
import type { PanelShell } from '../types';
import { iconButtonClass } from '../ui/classes';
import XqPanelHook from './XqPanelHook.vue';
import XqPanelNavItem from './XqPanelNavItem.vue';
import XqPanelNewMenu from './XqPanelNewMenu.vue';
import XqPanelSidebarCard from './XqPanelSidebarCard.vue';
import XqPanelTenantMenu from './XqPanelTenantMenu.vue';
import XqPanelUserMenu from './XqPanelUserMenu.vue';

const props = defineProps<{ panel: PanelShell; collapsed?: boolean }>();
defineEmits<{ collapse: []; search: [] }>();

const { glideClass, glideStyle, glideTo, hideGlide } = useGlidingHover();

const brandInitial = computed(() => props.panel.brand.name.charAt(0).toUpperCase());
</script>

<template>
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
                <PanelLeft class="size-4" aria-hidden="true" />
            </button>
        </div>

        <XqPanelHook name="sidebar.start" />

        <XqPanelTenantMenu v-if="panel.tenant && !collapsed" :tenant="panel.tenant" />

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
            class="gap-5 min-h-0 relative flex flex-1 flex-col overflow-y-auto"
            @mouseover="glideTo"
            @focusin="glideTo"
            @mouseleave="hideGlide"
            @focusout="hideGlide"
        >
            <span
                aria-hidden="true"
                :class="glideClass"
                :style="glideStyle"
                data-xq-nav-glide
            ></span>
            <div
                v-for="group in panel.navigation"
                :key="group.label ?? ''"
                role="group"
                :aria-label="group.label ?? undefined"
                class="gap-0.5 relative grid"
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

        <XqPanelHook name="sidebar.end" />

        <XqPanelSidebarCard v-if="panel.sidebarFooter && !collapsed" :card="panel.sidebarFooter" />

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
</template>
