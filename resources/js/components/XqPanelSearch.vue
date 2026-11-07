<script setup lang="ts">
import { t } from '../composables/useMessages';
import { router } from '@inertiajs/vue3';
import { HttpError, XqIcon, cn, postJson } from '@xelqun/forms';
import { Search } from 'lucide-vue-next';
import {
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { PanelNavigationGroup, PanelSearchGroup, PanelSearchResult } from '../types';

const MIN_SEARCH_LENGTH = 2;
const DEBOUNCE_MS = 250;

/** The session ended or access was taken away, and a reload shows the user which. */
const SIGNED_OUT_STATUSES = [401, 403, 419];

const props = defineProps<{ navigation: PanelNavigationGroup[]; searchUrl: string | null }>();
const open = defineModel<boolean>('open', { default: false });

const query = ref('');
const highlighted = ref(0);
const recordGroups = ref<PanelSearchGroup[]>([]);
const isSearching = ref(false);
const searchFailed = ref(false);

const pageGroup = computed<PanelSearchGroup>(() => {
    const search = query.value.trim().toLowerCase();

    return {
        label: t('Pages'),
        icon: null,
        results: props.navigation
            .flatMap((group) => group.items)
            .filter((item) => item.url && item.label.toLowerCase().includes(search))
            .map((item) => ({ title: item.label, url: item.url!, details: '', icon: item.icon })),
    };
});

const groups = computed(() =>
    [pageGroup.value, ...recordGroups.value].filter((group) => group.results.length > 0),
);
const results = computed(() => groups.value.flatMap((group) => group.results));

let debounce: ReturnType<typeof setTimeout> | undefined;
let request: AbortController | undefined;

function cancelSearch(): void {
    clearTimeout(debounce);
    request?.abort();
}

async function searchRecords(search: string): Promise<void> {
    const current = (request = new AbortController());

    try {
        const response = await postJson<{ groups: PanelSearchGroup[] }>(
            props.searchUrl!,
            { q: search },
            current.signal,
        );

        recordGroups.value = response.groups;
    } catch (error) {
        if (current.signal.aborted) {
            return;
        }

        if (error instanceof HttpError && SIGNED_OUT_STATUSES.includes(error.status)) {
            open.value = false;
            router.reload();
        }

        searchFailed.value = true;
    }

    isSearching.value = false;
}

// Old matches go right away, so Enter can't open one that no longer fits what was typed.
watch(query, (value) => {
    const search = value.trim();

    cancelSearch();
    highlighted.value = 0;
    recordGroups.value = [];
    searchFailed.value = false;
    isSearching.value = props.searchUrl !== null && search.length >= MIN_SEARCH_LENGTH;

    if (isSearching.value) {
        debounce = setTimeout(() => searchRecords(search), DEBOUNCE_MS);
    }
});

watch(open, (isOpen) => {
    if (isOpen) {
        query.value = '';
    } else {
        cancelSearch();
    }
});

onBeforeUnmount(cancelSearch);

function move(step: number): void {
    const count = results.value.length;

    if (count > 0) {
        highlighted.value = (highlighted.value + step + count) % count;
    }
}

function go(result: PanelSearchResult | undefined): void {
    if (result) {
        open.value = false;
        router.visit(result.url);
    }
}
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay
                class="inset-0 bg-black/40 data-[state=open]:animate-xq-fade-in data-[state=closed]:animate-xq-fade-out fixed z-50"
            />
            <DialogContent
                class="max-w-lg rounded-xq-xl border-xq-border bg-xq-popover text-xq-popover-foreground shadow-xl data-[state=open]:animate-xq-dialog-in data-[state=closed]:animate-xq-dialog-out fixed top-[15vh] left-1/2 z-50 w-[calc(100%-2rem)] -translate-x-1/2 overflow-hidden border outline-none"
                data-xq-search
            >
                <DialogTitle class="sr-only">{{ t('Search') }}</DialogTitle>
                <DialogDescription class="sr-only">
                    {{ t('Go to a page or a record of this panel.') }}
                </DialogDescription>
                <div class="gap-2 px-4 border-xq-border flex items-center border-b">
                    <Search class="size-4 text-xq-muted-foreground" aria-hidden="true" />
                    <input
                        v-model="query"
                        type="search"
                        :placeholder="t('Search')"
                        :aria-label="t('Search')"
                        maxlength="100"
                        class="py-3 text-sm w-full bg-transparent outline-none"
                        @keydown.down.prevent="move(1)"
                        @keydown.up.prevent="move(-1)"
                        @keydown.enter.prevent="go(results[highlighted])"
                    />
                </div>
                <div v-if="results.length > 0" class="p-1.5 max-h-96 overflow-y-auto">
                    <div v-for="group in groups" :key="group.label">
                        <p class="px-2.5 pt-2 pb-1 text-xs font-medium text-xq-muted-foreground">
                            {{ group.label }}
                        </p>
                        <ul>
                            <li
                                v-for="result in group.results"
                                :key="result.url"
                                class="animate-xq-rise-in"
                                :style="{
                                    animationDelay: `calc(var(--xq-stagger) * ${Math.min(results.indexOf(result), 6)})`,
                                }"
                            >
                                <button
                                    type="button"
                                    tabindex="-1"
                                    :class="
                                        cn(
                                            'gap-2.5 px-2.5 py-2 text-sm rounded-xq-sm flex w-full cursor-pointer items-center text-left transition-colors duration-(--xq-duration-fast)',
                                            results.indexOf(result) === highlighted &&
                                                'bg-xq-accent',
                                        )
                                    "
                                    @mouseenter="highlighted = results.indexOf(result)"
                                    @click="go(result)"
                                >
                                    <XqIcon
                                        v-if="result.icon ?? group.icon"
                                        :name="(result.icon ?? group.icon)!"
                                        class="size-4 text-xq-muted-foreground shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span class="min-w-0">
                                        <span class="block truncate">{{ result.title }}</span>
                                        <span
                                            v-if="result.details"
                                            class="text-xs text-xq-muted-foreground block truncate"
                                        >
                                            {{ result.details }}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
                <p
                    v-else-if="!isSearching && !searchFailed"
                    class="px-4 py-6 text-sm text-xq-muted-foreground text-center"
                >
                    {{ t('Nothing found.') }}
                </p>
                <p
                    v-if="isSearching || searchFailed"
                    role="status"
                    class="px-4 py-3 text-sm text-xq-muted-foreground border-xq-border border-t text-center"
                >
                    {{ isSearching ? t('Searching…') : t("Records can't be searched right now.") }}
                </p>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
