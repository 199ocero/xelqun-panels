<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, LogOut, Monitor, Moon, Settings2, Sun } from 'lucide-vue-next';
import {
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuPortal,
    DropdownMenuRoot,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from 'reka-ui';
import { computed } from 'vue';
import { setTheme, useThemeChoice } from '../composables/useTheme';
import type { ThemeChoice } from '../composables/useTheme';
import type { PanelShell } from '../types';
import { iconButtonClass, menuClass, menuItemClass } from '../ui/classes';

const props = defineProps<{
    user: NonNullable<PanelShell['user']>;
    logoutUrl: string | null;
    collapsed?: boolean;
}>();
const choice = useThemeChoice();

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
    <div class="gap-2.5 flex items-center" data-xq-user>
        <span
            aria-hidden="true"
            class="text-xs font-medium size-8 flex shrink-0 items-center justify-center rounded-full bg-xq-nav-active"
        >
            {{ initials }}
        </span>
        <div v-if="!collapsed" class="min-w-0 text-sm flex-1">
            <p class="font-medium truncate">{{ user.name }}</p>
            <p class="text-xs text-xq-muted-foreground truncate">{{ user.email }}</p>
        </div>
        <DropdownMenuRoot :modal="false">
            <DropdownMenuTrigger :class="iconButtonClass" aria-label="Open user menu">
                <Settings2 class="size-4" aria-hidden="true" />
            </DropdownMenuTrigger>
            <DropdownMenuPortal>
                <DropdownMenuContent :class="menuClass" side="top" align="end" :side-offset="6">
                    <DropdownMenuLabel class="px-2 py-1.5 text-xs text-xq-muted-foreground">
                        Theme
                    </DropdownMenuLabel>
                    <DropdownMenuItem
                        v-for="theme in themes"
                        :key="theme.value"
                        :class="menuItemClass"
                        @select="setTheme(theme.value)"
                    >
                        <component :is="theme.icon" class="size-4" aria-hidden="true" />
                        {{ theme.label }}
                        <Check
                            v-if="choice === theme.value"
                            class="size-4 ml-auto"
                            aria-hidden="true"
                        />
                    </DropdownMenuItem>
                    <template v-if="logoutUrl">
                        <DropdownMenuSeparator class="my-1 bg-xq-border h-px" />
                        <DropdownMenuItem :class="menuItemClass" @select="router.post(logoutUrl!)">
                            <LogOut class="size-4" aria-hidden="true" />
                            Sign out
                        </DropdownMenuItem>
                    </template>
                </DropdownMenuContent>
            </DropdownMenuPortal>
        </DropdownMenuRoot>
    </div>
</template>
