<script setup lang="ts">
import { Deferred, Head } from '@inertiajs/vue3';
import { XqActions, XqForm, cn } from '@xelqun/forms';
import { XqTable } from '@xelqun/tables';
import XqPanelHook from '../components/XqPanelHook.vue';
import XqPanelTabs from '../components/XqPanelTabs.vue';
import { computed, ref } from 'vue';
import XqPanelWidgets from '../components/widgets/XqPanelWidgets.vue';
import XqPanelLayout from '../layouts/XqPanelLayout.vue';
import type { PanelPageData, PanelWidget } from '../types';

defineOptions({ layout: XqPanelLayout });
const props = defineProps<{ page: PanelPageData; widgets?: PanelWidget[] }>();

/** Relation tables take turns, so a record with several stays one page tall. */
const relationTables = computed(() =>
    props.page.content.filter((item) => item.type === 'table' && item.heading),
);
const activeRelation = ref(0);
const hiddenRelations = computed(() =>
    relationTables.value.length > 1
        ? relationTables.value.filter((_, index) => index !== activeRelation.value)
        : [],
);
</script>

<template>
    <div
        class="gap-6 px-4 py-6 sm:px-8 sm:py-8 mx-auto grid max-w-(--xq-panel-content-width)"
        data-xq-page
    >
        <Head :title="page.title" />
        <XqPanelHook name="content.start" />
        <XqPanelTabs v-if="page.subNavigation.length > 0" :tabs="page.subNavigation" />
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
        <section
            v-if="page.notice"
            class="p-4 rounded-xq-lg border-xq-border bg-xq-card border"
            data-xq-notice
        >
            <h2 class="font-semibold">{{ page.notice.title }}</h2>
            <p class="mt-1 text-sm text-xq-muted-foreground">{{ page.notice.description }}</p>
            <ul class="mt-3 gap-1 text-sm sm:grid-cols-2 font-mono grid">
                <li v-for="line in page.notice.lines" :key="line">{{ line }}</li>
            </ul>
        </section>
        <XqPanelTabs v-if="page.tabs.length > 0" :tabs="page.tabs" />
        <Deferred v-if="page.hasWidgets" data="widgets">
            <template #fallback>
                <div class="gap-4 sm:grid-cols-2 lg:grid-cols-4 grid" data-xq-widgets-loading>
                    <div
                        v-for="placeholder in 4"
                        :key="placeholder"
                        class="h-36 xq-skeleton rounded-xq-lg animate-xq-skeleton"
                    ></div>
                </div>
            </template>
            <XqPanelWidgets v-if="widgets" :widgets="widgets" />
        </Deferred>
        <template v-for="item in page.content" :key="item.schema.id">
            <section
                v-if="item.type === 'table' && item.heading"
                v-show="!hiddenRelations.includes(item)"
                class="gap-3 grid"
            >
                <div
                    v-if="relationTables.length > 1"
                    role="tablist"
                    class="gap-1 border-xq-border flex overflow-x-auto border-b"
                    data-xq-relation-tabs
                >
                    <button
                        v-for="(relation, index) in relationTables"
                        :key="relation.schema.id"
                        type="button"
                        role="tab"
                        :aria-selected="relation === item"
                        :class="
                            cn(
                                'px-3 py-2 text-sm font-medium cursor-pointer border-b-2 whitespace-nowrap',
                                relation === item
                                    ? 'border-xq-primary'
                                    : 'text-xq-muted-foreground hover:text-xq-foreground border-transparent',
                            )
                        "
                        @click="activeRelation = index"
                    >
                        {{ relation.type === 'table' ? relation.heading : '' }}
                    </button>
                </div>
                <h2 v-else class="text-lg font-semibold">{{ item.heading }}</h2>
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
        <XqPanelHook name="content.end" />
    </div>
</template>
