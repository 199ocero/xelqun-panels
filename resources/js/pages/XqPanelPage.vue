<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { XqActions } from '@xelqun/forms';
import { XqTable } from '@xelqun/tables';
import XqPanelLayout from '../layouts/XqPanelLayout.vue';
import type { PanelPageData } from '../types';

defineOptions({ layout: XqPanelLayout });
defineProps<{ page: PanelPageData }>();
</script>

<template>
    <main data-xq-page>
        <Head :title="page.title" />
        <header class="gap-4 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">{{ page.title }}</h1>
            <XqActions v-if="page.headerActions" :actions="page.headerActions" />
        </header>
        <div class="mt-6 gap-6 grid">
            <template v-for="item in page.content" :key="item.schema.id">
                <XqTable v-if="item.type === 'table'" :table="item.schema" />
                <XqActions v-else :actions="item.schema" />
            </template>
        </div>
    </main>
</template>
