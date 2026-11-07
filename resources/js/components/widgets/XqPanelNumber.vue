<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ value: string }>();

const characters = computed(() => [...props.value]);
</script>

<template>
    <p :aria-label="value">
        <span :key="value" aria-hidden="true">
            <span
                v-for="(character, index) in characters"
                :key="index"
                class="inline-block animate-xq-digit-in whitespace-pre"
                :style="{ animationDelay: `calc(var(--xq-stagger) * ${Math.min(index, 10)})` }"
                >{{ character }}</span
            >
        </span>
    </p>
</template>
