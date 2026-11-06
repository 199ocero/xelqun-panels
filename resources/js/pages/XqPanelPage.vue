<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import XqPanelLayout from '../layouts/XqPanelLayout.vue';

defineOptions({ layout: XqPanelLayout });
defineProps<{ page: { title: string; next: string } }>();
</script>

<template>
    <main data-xq-page>
        <h1>{{ page.title }}</h1>
        <Link :href="page.next" data-xq-next>Next</Link>
    </main>
</template>
