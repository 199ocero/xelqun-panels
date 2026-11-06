<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { XqForm } from '@xelqun/forms';
import XqPanelAuthLayout from '../layouts/XqPanelAuthLayout.vue';
import type { PanelAuthPageData } from '../types';

defineOptions({ layout: XqPanelAuthLayout });
defineProps<{ page: PanelAuthPageData }>();
</script>

<template>
    <div
        class="max-w-sm gap-6 p-6 sm:p-8 border-xq-border bg-xq-card rounded-xq-xl grid w-full border"
        data-xq-auth-card
    >
        <Head :title="page.title" />
        <header>
            <h1 class="text-xl font-semibold tracking-tight">{{ page.title }}</h1>
            <p v-if="page.description" class="mt-1 text-sm text-xq-muted-foreground">
                {{ page.description }}
            </p>
        </header>
        <XqForm
            :key="page.action"
            :form="page.form"
            :action="page.action"
            :submit-label="page.submitLabel"
            :heading-level="2"
        />
    </div>
</template>
