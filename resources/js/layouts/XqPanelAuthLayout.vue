<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePanelDocument } from '../composables/usePanelDocument';
import type { PanelShell } from '../types';

const page = usePage();
const panel = computed(() => page.props.xelqunPanel as PanelShell);

usePanelDocument(panel);
</script>

<template>
    <main
        class="text-xq-foreground px-4 py-10 flex min-h-dvh flex-col items-center justify-center bg-xq-shell"
        data-xq-panel-auth
    >
        <Link :href="panel.url" class="gap-2.5 mb-6 font-semibold flex items-center">
            <span
                aria-hidden="true"
                class="text-xs size-6.5 bg-xq-foreground text-xq-background rounded-lg flex items-center justify-center"
            >
                {{ panel.brand.name.charAt(0).toUpperCase() }}
            </span>
            {{ panel.brand.name }}
        </Link>
        <slot />
    </main>
</template>
