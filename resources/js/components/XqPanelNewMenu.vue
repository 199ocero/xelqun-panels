<script setup lang="ts">
import { t } from '../composables/useMessages';
import { router } from '@inertiajs/vue3';
import { XqIcon, cn } from '@xelqun/forms';
import { Plus } from 'lucide-vue-next';
import {
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuTrigger,
} from 'reka-ui';
import type { PanelQuickCreateItem } from '../types';
import { menuClass, menuItemClass } from '../ui/classes';

defineProps<{ items: PanelQuickCreateItem[]; collapsed?: boolean }>();
</script>

<template>
    <DropdownMenuRoot :modal="false">
        <DropdownMenuTrigger
            :class="
                cn(
                    'gap-1.5 text-sm font-medium bg-xq-primary text-xq-primary-foreground focus-visible:ring-xq-ring h-8 inline-flex cursor-pointer items-center justify-center rounded-full transition-opacity hover:opacity-90 focus-visible:ring-2 focus-visible:outline-none',
                    collapsed ? 'w-8' : 'px-3 flex-1',
                )
            "
            :aria-label="t('New')"
            data-xq-new
        >
            <Plus class="size-4" aria-hidden="true" />
            <span v-if="!collapsed" aria-hidden="true">{{ t('New') }}</span>
        </DropdownMenuTrigger>
        <DropdownMenuPortal>
            <DropdownMenuContent
                :class="menuClass"
                :side="collapsed ? 'right' : 'bottom'"
                align="start"
                :side-offset="6"
                data-xq-new-menu
            >
                <DropdownMenuItem
                    v-for="item in items"
                    :key="item.url"
                    :class="menuItemClass"
                    @select="router.visit(item.url)"
                >
                    <XqIcon
                        v-if="item.icon"
                        :name="item.icon"
                        class="size-4 text-xq-muted-foreground"
                        aria-hidden="true"
                    />
                    {{ item.label }}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenuPortal>
    </DropdownMenuRoot>
</template>
