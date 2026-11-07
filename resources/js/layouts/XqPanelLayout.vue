<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { cn } from '@xelqun/forms';
import {
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import XqPanelNotifications from '../components/XqPanelNotifications.vue';
import XqPanelSearch from '../components/XqPanelSearch.vue';
import XqPanelSidebar from '../components/XqPanelSidebar.vue';
import XqPanelTopNav from '../components/XqPanelTopNav.vue';
import XqPanelTopbar from '../components/XqPanelTopbar.vue';
import XqPanelUserMenu from '../components/XqPanelUserMenu.vue';
import { t } from '../composables/useMessages';
import { usePanelDocument } from '../composables/usePanelDocument';
import type { PanelPageData, PanelShell } from '../types';

const COLLAPSED_KEY = 'xq-panel-sidebar-collapsed';

const page = usePage();
const panel = computed(() => page.props.xelqunPanel as PanelShell);
const breadcrumbs = computed(() =>
    panel.value.breadcrumbs
        ? ((page.props.page as PanelPageData | undefined)?.breadcrumbs ?? [])
        : [],
);

const collapsed = ref(false);
const drawerOpen = ref(false);
const searchOpen = ref(false);

usePanelDocument(panel);

function toggleCollapsed(): void {
    collapsed.value = !collapsed.value;

    try {
        localStorage.setItem(COLLAPSED_KEY, collapsed.value ? '1' : '0');
    } catch {
        // Storage can be blocked. The sidebar still holds for this visit.
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (
        panel.value.globalSearch &&
        (event.metaKey || event.ctrlKey) &&
        event.key.toLowerCase() === 'k'
    ) {
        event.preventDefault();
        searchOpen.value = true;
    }
}

const stopListening = router.on('navigate', () => {
    drawerOpen.value = false;
});

onMounted(() => {
    window.addEventListener('keydown', onKeydown);

    try {
        collapsed.value = localStorage.getItem(COLLAPSED_KEY) === '1';
    } catch {
        // Storage can be blocked. The sidebar then starts open.
    }
});

onBeforeUnmount(() => {
    stopListening();
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div class="text-xq-foreground flex h-dvh overflow-hidden bg-xq-shell" data-xq-panel-layout>
        <aside
            v-if="!panel.topNavigation"
            :class="
                cn(
                    'lg:block hidden shrink-0 transition-[width] duration-(--xq-duration-slow) ease-(--xq-ease-out)',
                    collapsed ? 'w-16' : 'w-70',
                )
            "
        >
            <XqPanelSidebar
                :panel="panel"
                :collapsed="collapsed"
                @collapse="toggleCollapsed"
                @search="searchOpen = true"
            />
        </aside>

        <DialogRoot v-model:open="drawerOpen">
            <DialogPortal>
                <DialogOverlay
                    class="inset-0 bg-black/40 lg:hidden data-[state=open]:animate-xq-fade-in data-[state=closed]:animate-xq-fade-out fixed z-40"
                />
                <DialogContent
                    class="inset-y-0 left-0 w-72 lg:hidden fixed z-50 bg-xq-shell outline-none data-[state=closed]:animate-xq-drawer-out data-[state=open]:animate-xq-drawer-in"
                >
                    <DialogTitle class="sr-only">{{ t('Navigation') }}</DialogTitle>
                    <DialogDescription class="sr-only">{{ panel.brand.name }}</DialogDescription>
                    <XqPanelSidebar
                        :panel="panel"
                        @collapse="drawerOpen = false"
                        @search="((drawerOpen = false), (searchOpen = true))"
                    />
                </DialogContent>
            </DialogPortal>
        </DialogRoot>

        <div
            class="lg:my-2 lg:mr-2 min-w-0 border-xq-border bg-xq-background lg:rounded-xq-xl lg:border flex flex-1 flex-col overflow-hidden"
        >
            <XqPanelTopbar
                :breadcrumbs="breadcrumbs"
                :searchable="panel.globalSearch"
                @menu="drawerOpen = true"
                @search="searchOpen = true"
            >
                <XqPanelNotifications
                    v-if="panel.notifications"
                    :url="panel.notifications.url"
                    :poll-seconds="panel.notifications.pollSeconds"
                    :broadcast-channel="panel.notifications.broadcastChannel"
                />
                <XqPanelUserMenu
                    v-if="panel.topNavigation && panel.user"
                    :user="panel.user"
                    :logout-url="panel.logoutUrl"
                    :profile-url="panel.profileUrl"
                    :items="panel.userMenuItems"
                    :can-change-theme="panel.theme.forced === null"
                    collapsed
                    class="ml-1 lg:flex hidden"
                />
            </XqPanelTopbar>
            <XqPanelTopNav v-if="panel.topNavigation" :navigation="panel.navigation" />
            <main class="min-h-0 flex-1 overflow-y-auto" scroll-region>
                <slot />
            </main>
        </div>

        <XqPanelSearch
            v-if="panel.globalSearch"
            v-model:open="searchOpen"
            :navigation="panel.navigation"
            :search-url="panel.searchUrl"
        />
    </div>
</template>
