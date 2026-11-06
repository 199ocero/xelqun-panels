<script setup lang="ts">
import { Deferred, Head, Link } from '@inertiajs/vue3';
import { XqActions, XqForm, XqIcon, cn } from '@xelqun/forms';
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
        <nav
            v-if="page.tabs.length > 0"
            aria-label="Views"
            class="gap-1 border-xq-border -mt-2 flex overflow-x-auto border-b"
            data-xq-tabs
        >
            <Link
                v-for="tab in page.tabs"
                :key="tab.key"
                :href="tab.url"
                :aria-current="tab.active ? 'page' : undefined"
                :class="
                    cn(
                        'gap-1.5 px-3 py-2 text-sm font-medium -mb-px flex shrink-0 items-center border-b-2 whitespace-nowrap',
                        tab.active
                            ? 'border-xq-primary text-xq-foreground'
                            : 'text-xq-muted-foreground hover:text-xq-foreground border-transparent',
                    )
                "
            >
                <XqIcon v-if="tab.icon" :name="tab.icon" class="size-4" aria-hidden="true" />
                {{ tab.label }}
                <span
                    v-if="tab.badge"
                    class="px-1.5 text-xs bg-xq-muted text-xq-muted-foreground rounded-full"
                >
                    {{ tab.badge }}
                </span>
            </Link>
        </nav>
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
            <section v-if="item.type === 'table' && item.heading" class="gap-3 grid">
                <h2 class="text-lg font-semibold">{{ item.heading }}</h2>
                <XqTable :table="item.schema" />
            </section>
            <XqTable v-else-if="item.type === 'table'" :table="item.schema" />
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
