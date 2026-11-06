import { onBeforeUnmount, watchEffect } from 'vue';
import type { Ref } from 'vue';
import type { PanelShell } from '../types';
import { useThemeSync } from './useTheme';

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

        root.setAttribute('data-xq-panel', '');
        root.style.setProperty('--xq-panel-font', `'${panel.value.theme.font}'`);

        if (panel.value.theme.primary) {
            root.style.setProperty('--xq-panel-primary', panel.value.theme.primary);
        } else {
            root.style.removeProperty('--xq-panel-primary');
        }
    });

    onBeforeUnmount(() => {
        const root = document.documentElement;

        root.removeAttribute('data-xq-panel');
        root.style.removeProperty('--xq-panel-font');
        root.style.removeProperty('--xq-panel-primary');
    });
}
