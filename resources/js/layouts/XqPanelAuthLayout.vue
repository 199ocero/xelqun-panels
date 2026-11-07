<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';
import { t } from '../composables/useMessages';
import { usePanelDocument } from '../composables/usePanelDocument';
import { setTheme } from '../composables/useTheme';
import type { PanelAuthPageData, PanelShell } from '../types';

const page = usePage();
const panel = computed(() => page.props.xelqunPanel as PanelShell);
const quote = computed(() => (page.props.page as PanelAuthPageData).quote);

usePanelDocument(panel);

function toggleTheme(): void {
    setTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
}
</script>

<template>
    <main
        class="text-xq-foreground lg:grid-cols-2 relative grid min-h-dvh bg-xq-shell"
        data-xq-panel-auth
    >
        <button
            v-if="!panel.theme.forced"
            type="button"
            class="top-4 left-4 size-9 text-xq-muted-foreground hover:bg-xq-accent hover:text-xq-foreground focus-visible:ring-xq-ring/40 absolute flex cursor-pointer items-center justify-center rounded-full transition-colors outline-none focus-visible:ring-[3px]"
            :aria-label="t('Theme')"
            @click="toggleTheme"
        >
            <Moon class="size-4" aria-hidden="true" data-xq-logo="light" />
            <Sun class="size-4" aria-hidden="true" data-xq-logo="dark" />
        </button>
        <div class="px-4 pt-16 pb-8 lg:max-w-sm max-w-md mx-auto flex w-full items-center">
            <slot />
        </div>
        <div class="p-3 min-h-80">
            <div
                class="p-6 sm:p-10 lg:p-12 rounded-xq-xl relative flex h-full items-end overflow-hidden"
                data-xq-auth-gradient
            >
                <span />
                <span />
                <span />
                <blockquote
                    class="max-w-2xl font-semibold tracking-tight text-white relative z-10 text-[clamp(1.75rem,3.4vw,3.5rem)] leading-[1.1] text-balance"
                >
                    {{ quote }}
                </blockquote>
            </div>
        </div>
    </main>
</template>
