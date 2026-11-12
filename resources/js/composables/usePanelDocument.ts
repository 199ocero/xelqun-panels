import { onBeforeUnmount, watchEffect } from 'vue';
import type { Ref } from 'vue';
import type { PanelShell } from '../types';
import { configureAppearance } from './useAppearance';
import { setMessages } from './useMessages';
import { clearMotion, configureMotion } from './useMotion';
import { configureTheme, useThemeSync } from './useTheme';

function clearColors(): void {
    const { style } = document.documentElement;

    [...style]
        .filter(
            (name) =>
                name.startsWith('--xq-color-') ||
                (name.startsWith('--xq-panel-') && name !== '--xq-panel-font'),
        )
        .forEach((name) => style.removeProperty(name));
}

/**
 * Puts a panel's look on `<html>`. A visit from outside the panel doesn't load
 * the root view, which is what normally does this.
 */
export function usePanelDocument(panel: Ref<PanelShell>): void {
    useThemeSync();

    watchEffect(() => {
        if (typeof document === 'undefined') {
            return;
        }

        const root = document.documentElement;

        clearColors();
        setMessages(panel.value.messages);
        configureTheme(panel.value.theme.default, panel.value.theme.forced);
        configureMotion(panel.value.theme.animations);
        root.setAttribute('data-xq-panel', '');
        root.style.setProperty('--xq-panel-font', `'${panel.value.theme.font}'`);

        Object.entries(panel.value.theme.colors).forEach(([variable, color]) =>
            root.style.setProperty(variable, color),
        );
        configureAppearance(
            panel.value.theme.preset,
            panel.value.theme.switcher,
            panel.value.theme.colors,
        );
    });

    onBeforeUnmount(() => {
        const root = document.documentElement;

        clearColors();
        clearMotion();
        root.removeAttribute('data-xq-panel');
        root.removeAttribute('data-xq-preset');
        root.style.removeProperty('--xq-panel-font');
    });
}
