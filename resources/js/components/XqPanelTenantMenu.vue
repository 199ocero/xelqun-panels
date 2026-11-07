<script setup lang="ts">
import { t } from '../composables/useMessages';
import { router } from '@inertiajs/vue3';
import { XqMenuGlide, cn } from '@xelqun/forms';
import { Check, ChevronsUpDown } from 'lucide-vue-next';
import {
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
    TooltipContent,
    TooltipPortal,
    TooltipRoot,
    TooltipTrigger,
} from 'reka-ui';
import { ref } from 'vue';
import type { PanelShell } from '../types';
import { menuClass, menuItemClass, sidebarTooltipClass } from '../ui/classes';

defineProps<{ tenant: NonNullable<PanelShell['tenant']>; collapsed?: boolean }>();

const isMenuOpen = ref(false);
</script>

<template>
    <TooltipRoot :disabled="!collapsed || isMenuOpen">
        <!-- Outside the menu, because both position with Popper and the inner one would take the trigger as its anchor. -->
        <TooltipTrigger as-child>
            <div class="grid">
                <DropdownMenuRoot v-model:open="isMenuOpen" :modal="false">
                    <DropdownMenuTrigger
                        :class="
                            cn(
                                'group gap-2 text-sm font-medium border-xq-border bg-xq-card hover:bg-xq-accent focus-visible:ring-xq-ring h-9 rounded-xq-md flex cursor-pointer items-center border transition-colors focus-visible:ring-2 focus-visible:outline-none',
                                collapsed
                                    ? 'size-9 mx-auto justify-center'
                                    : 'mx-1 px-2.5 justify-between',
                            )
                        "
                        :aria-label="t('Switch from :name', { name: tenant.name })"
                        data-xq-tenant
                    >
                        <span v-if="collapsed" aria-hidden="true">
                            {{ tenant.name.charAt(0).toUpperCase() }}
                        </span>
                        <template v-else>
                            <span class="truncate">{{ tenant.name }}</span>
                            <ChevronsUpDown
                                class="size-4 text-xq-muted-foreground shrink-0 transition-transform duration-(--xq-duration-base) ease-(--xq-ease-spring) group-data-[state=open]:scale-90"
                                aria-hidden="true"
                            />
                        </template>
                    </DropdownMenuTrigger>
                    <DropdownMenuPortal>
                        <DropdownMenuContent
                            :class="menuClass"
                            :side="collapsed ? 'right' : 'bottom'"
                            align="start"
                            :side-offset="collapsed ? 14 : 6"
                            data-xq-tenant-menu
                        >
                            <XqMenuGlide />
                            <DropdownMenuItem
                                v-for="option in tenant.tenants"
                                :key="option.url"
                                :class="menuItemClass"
                                @select="router.visit(option.url)"
                            >
                                {{ option.name }}
                                <Check
                                    v-if="option.active"
                                    class="size-4 ml-auto"
                                    aria-hidden="true"
                                />
                            </DropdownMenuItem>
                            <template v-if="tenant.profileUrl || tenant.registrationUrl">
                                <DropdownMenuSeparator class="my-1 bg-xq-border h-px" />
                                <DropdownMenuItem
                                    v-if="tenant.profileUrl"
                                    :class="menuItemClass"
                                    @select="router.visit(tenant.profileUrl!)"
                                >
                                    {{ t('Settings') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="tenant.registrationUrl"
                                    :class="menuItemClass"
                                    @select="router.visit(tenant.registrationUrl!)"
                                >
                                    {{ t('Add new') }}
                                </DropdownMenuItem>
                            </template>
                        </DropdownMenuContent>
                    </DropdownMenuPortal>
                </DropdownMenuRoot>
            </div>
        </TooltipTrigger>
        <TooltipPortal>
            <TooltipContent
                side="right"
                :side-offset="14"
                :class="sidebarTooltipClass"
                data-xq-nav-tooltip
            >
                {{ tenant.name }}
            </TooltipContent>
        </TooltipPortal>
    </TooltipRoot>
</template>
