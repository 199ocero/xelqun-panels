<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { XqActions, XqForm, XqTabGlide, cn } from '@xelqun/forms';
import { XqTable } from '@xelqun/tables';
import XqPanelHook from '../components/XqPanelHook.vue';
import XqPanelTabs from '../components/XqPanelTabs.vue';
import { computed, ref, shallowRef, watch } from 'vue';
import XqPanelWidgets from '../components/widgets/XqPanelWidgets.vue';
import XqPanelLayout from '../layouts/XqPanelLayout.vue';
import type { PanelPageData, PanelWidget } from '../types';

defineOptions({ layout: XqPanelLayout });
const props = defineProps<{ page: PanelPageData; widgets?: PanelWidget[] }>();

const inertiaPage = usePage();

/** A reload leaves deferred props out, so the widgets on screen stay until the new ones land. */
const loadedWidgets = shallowRef(props.widgets);

watch(
    () => props.widgets,
    (widgets) => {
        if (widgets) {
            loadedWidgets.value = widgets;
        }
    },
);

/** Saving and staying on the page isn't a visit, so it keeps the page rather than rebuilding it. */
function keepsPage(next: { url: string; props: Record<string, unknown> }): boolean {
    return next.url === inertiaPage.url || Object.keys(next.props.errors ?? {}).length > 0;
}

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
        <template v-if="page.hasWidgets">
            <XqPanelWidgets v-if="loadedWidgets" :widgets="loadedWidgets" />
            <div v-else class="gap-4 sm:grid-cols-2 lg:grid-cols-4 grid" data-xq-widgets-loading>
                <div
                    v-for="placeholder in 4"
                    :key="placeholder"
                    class="h-36 xq-skeleton rounded-xq-lg animate-xq-skeleton"
                ></div>
            </div>
        </template>
        <template v-for="item in page.content" :key="item.schema.id">
            <section
                v-if="item.type === 'table' && item.heading"
                v-show="!hiddenRelations.includes(item)"
                class="gap-3 grid"
            >
                <div
                    v-if="relationTables.length > 1"
                    role="tablist"
                    class="gap-1 border-xq-border relative flex overflow-x-auto border-b"
                    data-xq-relation-tabs
                >
                    <XqTabGlide class="bottom-0 h-0.5 bg-xq-primary" />
                    <button
                        v-for="(relation, index) in relationTables"
                        :key="relation.schema.id"
                        type="button"
                        role="tab"
                        :aria-selected="relation === item"
                        :class="
                            cn(
                                'px-3 py-2 text-sm font-medium cursor-pointer whitespace-nowrap transition-colors duration-(--xq-duration-fast)',
                                relation !== item &&
                                    'text-xq-muted-foreground hover:text-xq-foreground',
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
            <!-- The form rebuilds when its record changes. The wrapper keeps that from replaying the entrance. -->
            <div v-else-if="item.type === 'form'">
                <XqForm
                    :key="item.revision"
                    :form="item.schema"
                    :action="item.action"
                    :method="item.method"
                    :submit-label="item.submitLabel"
                    :preserve-state="keepsPage"
                    class="max-w-2xl"
                />
            </div>
            <XqActions v-else :actions="item.schema" />
        </template>
        <XqPanelHook name="content.end" />
    </div>
</template>
