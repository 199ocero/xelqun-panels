<script setup lang="ts">
import { t } from '../composables/useMessages';
import { router } from '@inertiajs/vue3';
import { XqIcon, XqMenuGlide } from '@xelqun/forms';
import {
    Check,
    ChevronsUpDown,
    LogOut,
    Monitor,
    Moon,
    Sparkles,
    Sun,
    UserRound,
} from '@lucide/vue';
import {
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuItemIndicator,
    DropdownMenuLabel,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from 'reka-ui';
import { computed } from 'vue';
import { setMotion, useMotion } from '../composables/useMotion';
import { setTheme, useThemeChoice } from '../composables/useTheme';
import type { ThemeChoice } from '../composables/useTheme';
import type { PanelNavigationItem, PanelShell } from '../types';
import { menuClass, menuItemClass } from '../ui/classes';

const props = defineProps<{
    user: NonNullable<PanelShell['user']>;
    logoutUrl: string | null;
    profileUrl: string | null;
    items?: PanelNavigationItem[];
    canChangeTheme?: boolean;
    collapsed?: boolean;
    menuSide?: 'top' | 'right' | 'bottom';
}>();
const choice = useThemeChoice();
const isMotionOn = useMotion();

const themes: Array<{ value: ThemeChoice; label: string; icon: typeof Sun }> = [
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'system', label: 'System', icon: Monitor },
];

const initials = computed(() =>
    props.user.name
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word.charAt(0).toUpperCase())
        .join(''),
);
</script>

<template>
    <div data-xq-user>
        <DropdownMenuRoot :modal="false">
            <DropdownMenuTrigger
                :class="[
                    'group gap-2.5 p-1 rounded-xq-sm flex w-full cursor-pointer items-center text-left transition-[background-color,scale] duration-(--xq-duration-fast) active:scale-[0.98]',
                    'focus-visible:ring-xq-ring hover:bg-xq-nav-hover focus-visible:ring-2 focus-visible:outline-none data-[state=open]:bg-xq-nav-hover',
                    collapsed && 'justify-center',
                ]"
                :aria-label="t('Open user menu')"
            >
                <span
                    aria-hidden="true"
                    class="text-xs font-medium size-8 flex shrink-0 items-center justify-center rounded-full bg-xq-nav-active"
                >
                    {{ initials }}
                </span>
                <span v-if="!collapsed" class="min-w-0 text-sm flex-1">
                    <span class="font-medium block truncate">{{ user.name }}</span>
                    <span class="text-xs text-xq-muted-foreground block truncate">
                        {{ user.email }}
                    </span>
                </span>
                <ChevronsUpDown
                    v-if="!collapsed"
                    class="size-4 mr-1.5 text-xq-muted-foreground group-hover:text-xq-foreground shrink-0 transition-[scale,color] duration-(--xq-duration-base) ease-(--xq-ease-spring) group-hover:scale-110"
                    aria-hidden="true"
                />
            </DropdownMenuTrigger>
            <DropdownMenuPortal>
                <DropdownMenuContent
                    :class="menuClass"
                    :side="menuSide ?? (collapsed ? 'right' : 'top')"
                    align="end"
                    :side-offset="menuSide !== 'bottom' && collapsed ? 14 : 6"
                >
                    <XqMenuGlide />
                    <template v-if="collapsed">
                        <DropdownMenuLabel class="px-2 py-1.5">
                            <span class="text-sm font-medium block truncate">{{ user.name }}</span>
                            <span class="text-xs text-xq-muted-foreground block truncate">
                                {{ user.email }}
                            </span>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator class="my-1 bg-xq-border h-px" />
                    </template>
                    <template v-if="canChangeTheme !== false">
                        <DropdownMenuLabel class="px-2 py-1.5 text-xs text-xq-muted-foreground">
                            {{ t('Theme') }}
                        </DropdownMenuLabel>
                        <DropdownMenuItem
                            v-for="theme in themes"
                            :key="theme.value"
                            :class="menuItemClass"
                            @select="setTheme(theme.value)"
                        >
                            <component :is="theme.icon" class="size-4" aria-hidden="true" />
                            {{ t(theme.label) }}
                            <Check
                                v-if="choice === theme.value"
                                class="size-4 ml-auto"
                                aria-hidden="true"
                            />
                        </DropdownMenuItem>
                    </template>
                    <DropdownMenuCheckboxItem
                        :class="menuItemClass"
                        :model-value="isMotionOn"
                        data-xq-motion-toggle
                        @update:model-value="setMotion"
                        @select.prevent
                    >
                        <Sparkles class="size-4" aria-hidden="true" />
                        {{ t('Animations') }}
                        <DropdownMenuItemIndicator class="ml-auto">
                            <Check class="size-4" aria-hidden="true" />
                        </DropdownMenuItemIndicator>
                    </DropdownMenuCheckboxItem>
                    <template v-if="logoutUrl">
                        <DropdownMenuSeparator
                            v-if="canChangeTheme !== false"
                            class="my-1 bg-xq-border h-px"
                        />
                        <DropdownMenuItem
                            v-for="item in items"
                            :key="item.label"
                            :class="menuItemClass"
                            @select="item.url && router.visit(item.url)"
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
                            v-if="profileUrl"
                            :class="menuItemClass"
                            @select="router.visit(profileUrl!)"
                        >
                            <UserRound class="size-4" aria-hidden="true" />
                            {{ t('Profile') }}
                        </DropdownMenuItem>
                        <DropdownMenuItem :class="menuItemClass" @select="router.post(logoutUrl!)">
                            <LogOut class="size-4" aria-hidden="true" />
                            {{ t('Sign out') }}
                        </DropdownMenuItem>
                    </template>
                </DropdownMenuContent>
            </DropdownMenuPortal>
        </DropdownMenuRoot>
    </div>
</template>
