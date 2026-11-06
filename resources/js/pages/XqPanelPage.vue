<script setup lang="ts">
import { Deferred, Head } from '@inertiajs/vue3';
import { XqActions, XqForm } from '@xelqun/forms';
import { XqTable } from '@xelqun/tables';
import XqPanelWidgets from '../components/widgets/XqPanelWidgets.vue';
import XqPanelLayout from '../layouts/XqPanelLayout.vue';
import type { PanelPageData, PanelWidget } from '../types';

defineOptions({ layout: XqPanelLayout });
defineProps<{ page: PanelPageData; widgets?: PanelWidget[] }>();
</script>

<template>
    <div class="gap-6 px-4 py-6 sm:px-8 sm:py-8 max-w-7xl mx-auto grid" data-xq-page>
        <Head :title="page.title" />
        <header class="gap-4 flex flex-wrap items-start justify-between">
            <div class="min-w-0">
                <p v-if="page.eyebrow" class="text-sm text-xq-muted-foreground">
                    {{ page.eyebrow }}
                </p>
                <h1 class="text-2xl font-semibold sm:text-3xl tracking-tight">{{ page.title }}</h1>
                <p v-if="page.subheading" class="mt-1 text-sm text-xq-muted-foreground">
                    {{ page.subheading }}
                </p>
            </div>
            <XqActions v-if="page.headerActions" :actions="page.headerActions" />
        </header>
        <Deferred v-if="page.hasWidgets" data="widgets">
            <template #fallback>
                <div class="gap-4 sm:grid-cols-2 lg:grid-cols-4 grid" data-xq-widgets-loading>
                    <div
                        v-for="placeholder in 4"
                        :key="placeholder"
                        class="h-36 bg-xq-muted animate-pulse rounded-xq-lg"
                    ></div>
                </div>
            </template>
            <XqPanelWidgets v-if="widgets" :widgets="widgets" />
        </Deferred>
        <template v-for="item in page.content" :key="item.schema.id">
            <XqTable v-if="item.type === 'table'" :table="item.schema" />
            <XqForm
                v-else-if="item.type === 'form'"
                :key="item.revision"
                :form="item.schema"
                :action="item.action"
                :method="item.method"
                :submit-label="item.submitLabel"
                class="max-w-2xl"
            />
            <XqActions v-else :actions="item.schema" />
        </template>
    </div>
</template>
