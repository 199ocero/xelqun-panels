<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { PanelShell } from '../types';

const panel = computed(() => usePage().props.xelqunPanel as PanelShell);
</script>

<template>
    <div data-xq-panel-layout>
        <header>
            <Link :href="panel.url">{{ panel.brand.name }}</Link>
        </header>
        <slot />
    </div>
</template>
