<script setup lang="ts">
import { XqIcon } from '@xelqun/forms';
import type { PanelSidebarCard } from '../types';

defineProps<{ card: PanelSidebarCard }>();

const SEGMENTS = 24;
</script>

<template>
    <div
        class="gap-3 p-3 rounded-xq-lg border-xq-border bg-xq-card text-sm grid border"
        data-xq-sidebar-card
    >
        <div class="gap-2 flex items-center">
            <XqIcon
                v-if="card.icon"
                :name="card.icon"
                class="size-4 text-xq-muted-foreground"
                aria-hidden="true"
            />
            <span class="font-medium">{{ card.title }}</span>
            <span
                v-if="card.badge"
                class="px-2 text-xs bg-xq-secondary text-xq-secondary-foreground py-0.5 ml-auto rounded-(--xq-pill-radius)"
            >
                {{ card.badge }}
            </span>
        </div>
        <p v-if="card.value">
            <span class="text-lg font-semibold tabular-nums">{{ card.value }}</span>
            <span v-if="card.note" class="ml-1.5 text-xq-muted-foreground">{{ card.note }}</span>
        </p>
        <div
            v-if="card.progress !== null"
            role="progressbar"
            :aria-label="card.title"
            :aria-valuenow="Math.round(card.progress * 100)"
            aria-valuemin="0"
            aria-valuemax="100"
            class="gap-0.5 flex"
        >
            <span
                v-for="segment in SEGMENTS"
                :key="segment"
                class="h-1.5 flex-1 rounded-full"
                :class="segment / SEGMENTS <= card.progress ? 'bg-xq-primary' : 'bg-xq-muted'"
            ></span>
        </div>
        <div
            v-if="card.footer || card.link"
            class="text-xs text-xq-muted-foreground flex items-center justify-between"
        >
            <span>{{ card.footer }}</span>
            <a
                v-if="card.link"
                :href="card.link.url"
                class="font-medium text-xq-primary hover:underline"
            >
                {{ card.link.label }}
            </a>
        </div>
    </div>
</template>
