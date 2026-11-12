<script setup lang="ts">
import { t } from '../composables/useMessages';
import { router } from '@inertiajs/vue3';
import { XqIcon, XqMenuGlide } from '@xelqun/forms';
import {
    Check,
    ChevronRight,
    ChevronsUpDown,
    LogOut,
    Monitor,
    Moon,
    Palette,
    RotateCcw,
    Shapes,
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
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuRoot,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from 'reka-ui';
import { computed } from 'vue';
import {
    currentPreset,
    resetAppearance,
    setAppearance,
    useAppearance,
    useAppearanceSwitcher,
} from '../composables/useAppearance';
import { setMotion, useMotion } from '../composables/useMotion';
import { setTheme, useThemeChoice } from '../composables/useTheme';
import { visitUrl } from '../lib/url';
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
const switcher = useAppearanceSwitcher();
const appearance = useAppearance();

const presetLabels: Record<string, string> = {
    default: 'Default',
    soft: 'Soft',
    sharp: 'Sharp',
    mono: 'Mono',
};

/** Primary takes the colors, secondary the grays. */
const colorRoles = [
    { key: 'primary', label: 'Primary', isGray: false },
    { key: 'secondary', label: 'Secondary', isGray: true },
] as const;

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
                    <template v-if="switcher">
                        <DropdownMenuSub>
                            <DropdownMenuSubTrigger :class="menuItemClass" data-xq-style-menu>
                                <Shapes class="size-4" aria-hidden="true" />
                                {{ t('Style') }}
                                <ChevronRight class="size-4 ml-auto" aria-hidden="true" />
                            </DropdownMenuSubTrigger>
                            <DropdownMenuPortal>
                                <DropdownMenuSubContent :class="menuClass" :side-offset="6">
                                    <DropdownMenuRadioGroup
                                        :model-value="currentPreset"
                                        @update:model-value="
                                            setAppearance({ preset: String($event) })
                                        "
                                    >
                                        <DropdownMenuRadioItem
                                            v-for="preset in switcher.presets"
                                            :key="preset"
                                            :value="preset"
                                            :class="menuItemClass"
                                            @select.prevent
                                        >
                                            {{ t(presetLabels[preset] ?? preset) }}
                                            <DropdownMenuItemIndicator class="ml-auto">
                                                <Check class="size-4" aria-hidden="true" />
                                            </DropdownMenuItemIndicator>
                                        </DropdownMenuRadioItem>
                                    </DropdownMenuRadioGroup>
                                </DropdownMenuSubContent>
                            </DropdownMenuPortal>
                        </DropdownMenuSub>
                        <DropdownMenuSub>
                            <DropdownMenuSubTrigger :class="menuItemClass" data-xq-colors-menu>
                                <Palette class="size-4" aria-hidden="true" />
                                {{ t('Colors') }}
                                <ChevronRight class="size-4 ml-auto" aria-hidden="true" />
                            </DropdownMenuSubTrigger>
                            <DropdownMenuPortal>
                                <DropdownMenuSubContent
                                    :class="[menuClass, 'w-64']"
                                    :side-offset="6"
                                >
                                    <template v-for="role in colorRoles" :key="role.key">
                                        <DropdownMenuLabel
                                            class="px-2 py-1.5 text-xs text-xq-muted-foreground"
                                        >
                                            {{ t(role.label) }}
                                        </DropdownMenuLabel>
                                        <DropdownMenuRadioGroup
                                            :model-value="appearance[role.key] ?? ''"
                                            class="gap-1.5 px-2 pb-2 grid grid-cols-9"
                                            @update:model-value="
                                                setAppearance({ [role.key]: String($event) })
                                            "
                                        >
                                            <DropdownMenuRadioItem
                                                v-for="color in switcher.palette.filter(
                                                    (color) => color.gray === role.isGray,
                                                )"
                                                :key="color.name"
                                                :value="color.name"
                                                :aria-label="color.name"
                                                :title="color.name"
                                                class="size-5 ring-offset-xq-popover data-[highlighted]:ring-xq-ring data-[state=checked]:ring-xq-foreground cursor-pointer rounded-full ring-offset-2 outline-none data-[highlighted]:ring-2 data-[state=checked]:ring-2"
                                                :style="{ background: color.fill }"
                                                @select.prevent
                                            />
                                        </DropdownMenuRadioGroup>
                                    </template>
                                    <DropdownMenuSeparator class="my-1 bg-xq-border h-px" />
                                    <DropdownMenuItem
                                        :class="menuItemClass"
                                        @select.prevent="resetAppearance()"
                                    >
                                        <RotateCcw class="size-4" aria-hidden="true" />
                                        {{ t('Reset style and colors') }}
                                    </DropdownMenuItem>
                                </DropdownMenuSubContent>
                            </DropdownMenuPortal>
                        </DropdownMenuSub>
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
