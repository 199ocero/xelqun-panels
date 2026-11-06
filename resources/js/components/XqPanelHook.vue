<script setup lang="ts">
import { useRegistry } from '@xelqun/forms';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { PanelShell } from '../types';

const props = defineProps<{ name: string }>();

const page = usePage();
const registry = useRegistry();

const items = computed(() =>
    ((page.props.xelqunPanel as PanelShell).hooks[props.name] ?? []).flatMap((hook) => {
        const component = registry?.get(hook.component);

        return component ? [{ ...hook, component }] : [];
    }),
);
</script>

<template>
    <component
        :is="item.component"
        v-for="(item, index) in items"
        :key="index"
        v-bind="item.props"
    />
</template>
