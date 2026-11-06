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
import XqPanelSearch from '../components/XqPanelSearch.vue';
import XqPanelSidebar from '../components/XqPanelSidebar.vue';
import XqPanelTopbar from '../components/XqPanelTopbar.vue';
import { usePanelDocument } from '../composables/usePanelDocument';
import type { PanelPageData, PanelShell } from '../types';

const COLLAPSED_KEY = 'xq-panel-sidebar-collapsed';

const page = usePage();
const panel = computed(() => page.props.xelqunPanel as PanelShell);
const breadcrumbs = computed(
    () => (page.props.page as PanelPageData | undefined)?.breadcrumbs ?? [],
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
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
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
            :class="
                cn(
                    'lg:block hidden shrink-0 transition-[width] duration-200',
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
                <DialogOverlay class="inset-0 bg-black/40 lg:hidden fixed z-40" />
                <DialogContent
                    class="inset-y-0 left-0 w-72 lg:hidden fixed z-50 bg-xq-shell outline-none"
                >
                    <DialogTitle class="sr-only">Navigation</DialogTitle>
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
                @menu="drawerOpen = true"
                @search="searchOpen = true"
            />
            <main class="min-h-0 flex-1 overflow-y-auto" scroll-region>
                <slot />
            </main>
        </div>

        <XqPanelSearch
            v-model:open="searchOpen"
            :navigation="panel.navigation"
            :search-url="panel.searchUrl"
        />
    </div>
</template>
