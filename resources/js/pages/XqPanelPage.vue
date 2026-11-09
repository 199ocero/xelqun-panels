<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { XqActions, XqButton, XqForm, XqTabGlide, cn } from '@xelqun/forms';
import type { FormStore } from '@xelqun/forms';
import { XqTable } from '@xelqun/tables';
import XqPanelHook from '../components/XqPanelHook.vue';
import { t } from '../composables/useMessages';
import XqPanelTabs from '../components/XqPanelTabs.vue';
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import XqPanelWidgets from '../components/widgets/XqPanelWidgets.vue';
import XqPanelLayout from '../layouts/XqPanelLayout.vue';
import type { PanelPageData, PanelWidget } from '../types';

defineOptions({ layout: XqPanelLayout });
const props = defineProps<{
    page: PanelPageData;
    widgets?: PanelWidget[];
    footerWidgets?: PanelWidget[];
}>();

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

const loadedFooterWidgets = shallowRef(props.footerWidgets);

watch(
    () => props.footerWidgets,
    (widgets) => {
        if (widgets) {
            loadedFooterWidgets.value = widgets;
        }
    },
);

/** The new filter values go in the URL, and only the widgets load again. */
function applyFilters(form: FormStore): void {
    router.get(
        window.location.pathname,
        { filters: form.state.data },
        { preserveState: true, preserveScroll: true, only: ['widgets', 'footerWidgets', 'page'] },
    );
}

// The widgets reload together, as often as the most eager of them asks.
const pollSeconds = computed(() => {
    const intervals = [...(loadedWidgets.value ?? []), ...(loadedFooterWidgets.value ?? [])]
        .map((widget) => widget.pollSeconds)
        .filter((seconds): seconds is number => !!seconds);

    return intervals.length ? Math.min(...intervals) : null;
});
let pollTimer: ReturnType<typeof setInterval> | undefined;

function startPolling(seconds: number | null): void {
    clearInterval(pollTimer);

    if (seconds) {
        pollTimer = setInterval(() => {
            if (!document.hidden) {
                router.reload({ only: ['widgets', 'footerWidgets'] });
            }
        }, seconds * 1000);
    }
}

onMounted(() => startPolling(pollSeconds.value));
watch(pollSeconds, startPolling);
onBeforeUnmount(() => clearInterval(pollTimer));

/** Saving and staying on the page isn't a visit, so it keeps the page rather than rebuilding it. */
function keepsPage(next: { url: string; props: Record<string, unknown> }): boolean {
    return next.url === inertiaPage.url || Object.keys(next.props.errors ?? {}).length > 0;
}

type RelationItem = Extract<PanelPageData['content'][number], { type: 'table' }>;

/** Relation tables take turns, so a record with several stays one page tall. */
const relationTables = computed(
    () =>
        props.page.content.filter(
            (item) => item.type === 'table' && item.heading,
        ) as RelationItem[],
);
const tabOf = (item: RelationItem) => item.group ?? item.heading ?? '';
/** One tab per manager, and one for each RelationGroup, in order. */
const relationTabs = computed(() => {
    const tabs: Array<{ label: string; badge?: string; badgeColor?: string }> = [];

    for (const item of relationTables.value) {
        if (!tabs.some((tab) => tab.label === tabOf(item))) {
            tabs.push({
                label: tabOf(item),
                badge: item.group ? undefined : item.badge,
                badgeColor: item.badgeColor,
            });
        }
    }

    return tabs;
});
const isStacked = computed(() => props.page.relationsLayout === 'stacked');
const activeRelation = ref(0);
const usesTabs = computed(() => !isStacked.value && relationTabs.value.length > 1);

function isHidden(item: RelationItem): boolean {
    return usesTabs.value && tabOf(item) !== relationTabs.value[activeRelation.value]?.label;
}

const badgeColors: Record<string, string> = {
    primary: 'bg-xq-primary/15 text-xq-primary',
    danger: 'bg-xq-destructive/15 text-xq-destructive',
    info: 'bg-xq-info/15 text-xq-info',
    success: 'bg-xq-success/15 text-xq-success',
    warning: 'bg-xq-warning/15 text-xq-warning',
    gray: 'bg-xq-muted text-xq-muted-foreground',
};
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
        <section
            v-if="page.filters"
            class="p-4 rounded-xq-lg border-xq-border bg-xq-card border"
            data-xq-page-filters
        >
            <XqForm :form="page.filters" @submit="applyFilters">
                <template #actions="{ submit }">
                    <div class="flex justify-end">
                        <button
                            type="button"
                            class="text-sm font-medium text-xq-primary cursor-pointer hover:underline"
                            data-xq-apply-filters
                            @click="submit"
                        >
                            {{ t('Apply filters') }}
                        </button>
                    </div>
                </template>
            </XqForm>
        </section>
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
            <div
                v-if="
                    item.type === 'table' && item.heading && usesTabs && item === relationTables[0]
                "
                role="tablist"
                class="gap-1 border-xq-border relative flex [scrollbar-width:none] overflow-x-auto border-b"
                data-xq-relation-tabs
            >
                <XqTabGlide class="bottom-0 h-0.5 bg-xq-primary" />
                <button
                    v-for="(tab, index) in relationTabs"
                    :key="tab.label"
                    type="button"
                    role="tab"
                    :aria-selected="index === activeRelation"
                    :class="
                        cn(
                            'gap-1.5 px-3 py-2 text-sm font-medium inline-flex cursor-pointer items-center whitespace-nowrap transition-colors duration-(--xq-duration-fast)',
                            index !== activeRelation &&
                                'text-xq-muted-foreground hover:text-xq-foreground',
                        )
                    "
                    @click="activeRelation = index"
                >
                    {{ tab.label }}
                    <span
                        v-if="tab.badge"
                        :class="
                            cn(
                                'px-1.5 text-xs rounded-full tabular-nums',
                                badgeColors[tab.badgeColor ?? 'gray'] ?? badgeColors.gray,
                            )
                        "
                        data-xq-relation-badge
                    >
                        {{ tab.badge }}
                    </span>
                </button>
            </div>
            <section
                v-if="item.type === 'table' && item.heading"
                v-show="!isHidden(item)"
                class="gap-3 grid"
            >
                <h2 v-if="!usesTabs || item.group" class="text-lg font-semibold">
                    {{ item.heading }}
                </h2>
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
                >
                    <template
                        v-if="item.createAnotherLabel"
                        #actions="{ store, processing, submit }"
                    >
                        <div class="gap-3 flex items-center justify-end">
                            <XqButton
                                type="button"
                                variant="outline"
                                :disabled="processing"
                                data-xq-create-another
                                @click="
                                    store.submit(`${item.action}?another=1`, {
                                        method: item.method,
                                        preserveState: keepsPage,
                                    })
                                "
                            >
                                {{ item.createAnotherLabel }}
                            </XqButton>
                            <XqButton type="button" :disabled="processing" @click="submit">
                                {{ item.submitLabel }}
                            </XqButton>
                        </div>
                    </template>
                </XqForm>
            </div>
            <XqActions v-else :actions="item.schema" />
        </template>
        <template v-if="page.hasFooterWidgets">
            <XqPanelWidgets v-if="loadedFooterWidgets" :widgets="loadedFooterWidgets" />
            <div v-else class="h-36 xq-skeleton rounded-xq-lg animate-xq-skeleton"></div>
        </template>
        <XqPanelHook name="content.end" />
    </div>
</template>
