<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { XqIcon, cn } from '@xelqun/forms';
import { Search } from 'lucide-vue-next';
import {
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { computed, ref, watch } from 'vue';
import type { PanelNavigationGroup, PanelNavigationItem } from '../types';

const props = defineProps<{ navigation: PanelNavigationGroup[] }>();
const open = defineModel<boolean>('open', { default: false });

const query = ref('');
const highlighted = ref(0);

const results = computed<PanelNavigationItem[]>(() => {
    const search = query.value.trim().toLowerCase();

    return props.navigation
        .flatMap((group) => group.items)
        .filter((item) => item.url && item.label.toLowerCase().includes(search));
});

watch(results, () => (highlighted.value = 0));
watch(open, (isOpen) => isOpen && (query.value = ''));

function move(step: number): void {
    const count = results.value.length;

    if (count > 0) {
        highlighted.value = (highlighted.value + step + count) % count;
    }
}

function go(item: PanelNavigationItem | undefined): void {
    if (item?.url) {
        open.value = false;
        router.visit(item.url);
    }
}
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="inset-0 bg-black/40 fixed z-50" />
            <DialogContent
                class="max-w-lg rounded-xq-xl border-xq-border bg-xq-popover text-xq-popover-foreground shadow-xl fixed top-[15vh] left-1/2 z-50 w-[calc(100%-2rem)] -translate-x-1/2 overflow-hidden border outline-none"
                data-xq-search
            >
                <DialogTitle class="sr-only">Search</DialogTitle>
                <DialogDescription class="sr-only">Go to a page of this panel.</DialogDescription>
                <div class="gap-2 px-4 border-xq-border flex items-center border-b">
                    <Search class="size-4 text-xq-muted-foreground" aria-hidden="true" />
                    <input
                        v-model="query"
                        type="search"
                        placeholder="Search"
                        aria-label="Search"
                        class="py-3 text-sm w-full bg-transparent outline-none"
                        @keydown.down.prevent="move(1)"
                        @keydown.up.prevent="move(-1)"
                        @keydown.enter.prevent="go(results[highlighted])"
                    />
                </div>
                <ul v-if="results.length > 0" class="p-1.5 max-h-80 overflow-y-auto">
                    <li v-for="(item, index) in results" :key="item.url!">
                        <button
                            type="button"
                            tabindex="-1"
                            :class="
                                cn(
                                    'gap-2.5 px-2.5 py-2 text-sm rounded-xq-sm flex w-full cursor-pointer items-center',
                                    index === highlighted && 'bg-xq-accent',
                                )
                            "
                            @mouseenter="highlighted = index"
                            @click="go(item)"
                        >
                            <XqIcon
                                v-if="item.icon"
                                :name="item.icon"
                                class="size-4 text-xq-muted-foreground"
                                aria-hidden="true"
                            />
                            {{ item.label }}
                        </button>
                    </li>
                </ul>
                <p v-else class="px-4 py-6 text-sm text-xq-muted-foreground text-center">
                    Nothing found.
                </p>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
