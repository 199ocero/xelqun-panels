<script setup lang="ts">
import { t } from '../composables/useMessages';
import { router } from '@inertiajs/vue3';
import { XqIcon, XqMenuGlide, cn } from '@xelqun/forms';
import { postJson } from '@xelqun/forms/internal';
import { Check, ChevronsUpDown } from '@lucide/vue';
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
import { computed, ref, watch } from 'vue';
import type { PanelShell, PanelTenantOption } from '../types';
import { menuClass, menuItemClass, sidebarTooltipClass } from '../ui/classes';
import { visitUrl } from '../lib/url';

const props = defineProps<{ tenant: NonNullable<PanelShell['tenant']>; collapsed?: boolean }>();

const isMenuOpen = ref(false);
const search = ref('');
const found = ref<PanelTenantOption[] | null>(null);
const options = computed(() => found.value ?? props.tenant.tenants);
let timer: ReturnType<typeof setTimeout> | undefined;
let latest = 0;

// Only some tenants come with the page, so the rest are found on the server by name.
watch(search, (term) => {
    clearTimeout(timer);

    // An older answer that arrives late, or after the box was cleared, must not replace a newer list.
    const mine = ++latest;

    if (!props.tenant.searchUrl || term.trim() === '') {
        found.value = null;

        return;
    }

    timer = setTimeout(async () => {
        try {
            found.value = (
                await postJson<{ tenants: PanelTenantOption[] }>(props.tenant.searchUrl!, {
                    q: term,
                })
            ).tenants;
        } catch {
            found.value = [];
        }
    }, 250);
});

watch(isMenuOpen, (open) => {
    if (!open) {
        search.value = '';
    }
});
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
                            <!-- Keys stay in the box, or the menu would take them to jump between items. Escape still closes it. -->
                            <input
                                v-if="tenant.searchUrl"
                                v-model="search"
                                type="search"
                                :placeholder="t('Search')"
                                :aria-label="t('Search')"
                                class="mb-1 h-8 px-2 text-sm rounded-xq-sm border-xq-border bg-xq-background focus-visible:border-xq-ring w-full border outline-none"
                                data-xq-tenant-search
                                @keydown="
                                    (event) => event.key !== 'Escape' && event.stopPropagation()
                                "
                            />
                            <DropdownMenuItem
                                v-for="option in options"
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
                            <template
                                v-if="
                                    tenant.profileUrl ||
                                    tenant.registrationUrl ||
                                    tenant.menuItems?.length
                                "
                            >
                                <DropdownMenuSeparator class="my-1 bg-xq-border h-px" />
                                <DropdownMenuItem
                                    v-for="item in tenant.menuItems ?? []"
                                    :key="item.label"
                                    :class="menuItemClass"
                                    data-xq-tenant-menu-item
                                    @select="item.url && visitUrl(item.url)"
                                >
                                    <XqIcon
                                        v-if="item.icon"
                                        :name="item.icon"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    {{ item.label }}
                                </DropdownMenuItem>
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
