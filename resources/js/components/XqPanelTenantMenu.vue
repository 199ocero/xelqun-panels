<script setup lang="ts">
import { t } from '../composables/useMessages';
import { router } from '@inertiajs/vue3';
import { Check, ChevronsUpDown } from 'lucide-vue-next';
import {
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuTrigger,
} from 'reka-ui';
import type { PanelShell } from '../types';
import { menuClass, menuItemClass } from '../ui/classes';

defineProps<{ tenant: NonNullable<PanelShell['tenant']> }>();
</script>

<template>
    <DropdownMenuRoot :modal="false">
        <DropdownMenuTrigger
            class="gap-2 mx-1 px-2.5 text-sm font-medium border-xq-border bg-xq-card hover:bg-xq-accent focus-visible:ring-xq-ring h-9 rounded-xq-md flex cursor-pointer items-center justify-between border transition-colors focus-visible:ring-2 focus-visible:outline-none"
            :aria-label="t('Switch from :name', { name: tenant.name })"
            data-xq-tenant
        >
            <span class="truncate">{{ tenant.name }}</span>
            <ChevronsUpDown class="size-4 text-xq-muted-foreground shrink-0" aria-hidden="true" />
        </DropdownMenuTrigger>
        <DropdownMenuPortal>
            <DropdownMenuContent
                :class="menuClass"
                align="start"
                :side-offset="6"
                data-xq-tenant-menu
            >
                <DropdownMenuItem
                    v-for="option in tenant.tenants"
                    :key="option.url"
                    :class="menuItemClass"
                    @select="router.visit(option.url)"
                >
                    {{ option.name }}
                    <Check v-if="option.active" class="size-4 ml-auto" aria-hidden="true" />
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenuPortal>
    </DropdownMenuRoot>
</template>
