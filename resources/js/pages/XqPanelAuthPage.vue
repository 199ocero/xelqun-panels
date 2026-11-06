<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { XqForm } from '@xelqun/forms';
import XqPanelHook from '../components/XqPanelHook.vue';
import XqPanelAuthLayout from '../layouts/XqPanelAuthLayout.vue';
import type { PanelAuthPageData } from '../types';

defineOptions({ layout: XqPanelAuthLayout });
defineProps<{ page: PanelAuthPageData }>();
</script>

<template>
    <div
        class="max-w-sm gap-6 p-6 sm:p-8 border-xq-border bg-xq-card rounded-xq-xl grid w-full border"
        data-xq-auth-card
    >
        <Head :title="page.title" />
        <header>
            <h1 class="text-xl font-semibold tracking-tight">{{ page.title }}</h1>
            <p v-if="page.description" class="mt-1 text-sm text-xq-muted-foreground">
                {{ page.description }}
            </p>
        </header>
        <XqPanelHook name="auth-form.before" />
        <XqForm
            :key="page.action"
            :form="page.form"
            :action="page.action"
            :submit-label="page.submitLabel"
            :heading-level="2"
        />
        <XqPanelHook name="auth-form.after" />
        <nav
            v-if="page.links.length > 0"
            class="gap-x-4 gap-y-1 text-sm flex flex-wrap justify-center"
        >
            <Link
                v-for="link in page.links"
                :key="link.label"
                :href="link.url"
                :method="link.method"
                :as="link.method === 'post' ? 'button' : 'a'"
                class="text-xq-primary cursor-pointer hover:underline"
            >
                {{ link.label }}
            </Link>
        </nav>
    </div>
</template>
