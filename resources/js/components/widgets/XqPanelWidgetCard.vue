<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{ heading: string | null; count?: number; seeAllUrl?: string | null }>();
</script>

<template>
    <section
        class="border-xq-border bg-xq-card rounded-xq-lg flex h-full flex-col border"
        data-xq-widget-card
    >
        <header v-if="heading" class="gap-2 px-5 pt-4 pb-2 flex items-center">
            <h2 class="font-semibold">{{ heading }}</h2>
            <span
                v-if="count !== undefined"
                class="px-2 text-xs bg-xq-muted text-xq-muted-foreground py-0.5 rounded-full tabular-nums"
            >
                {{ count }}
            </span>
            <Link
                v-if="seeAllUrl"
                :href="seeAllUrl"
                class="text-sm font-medium ml-auto hover:underline"
            >
                See all
            </Link>
        </header>
        <slot />
    </section>
</template>
