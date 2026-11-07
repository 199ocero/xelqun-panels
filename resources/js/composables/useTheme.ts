import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useMotion } from './useMotion';

export type ThemeChoice = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'xq-panel-theme';

const choice = ref<ThemeChoice>('system');

let defaultChoice: ThemeChoice = 'system';
let forcedChoice: ThemeChoice | null = null;
let isSynced = false;

function read(): ThemeChoice {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);

        return saved === 'light' || saved === 'dark' || saved === 'system' ? saved : defaultChoice;
    } catch {
        return defaultChoice;
    }
}

function apply(): void {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const theme = forcedChoice ?? choice.value;

    document.documentElement.classList.toggle(
        'dark',
        theme === 'dark' || (theme === 'system' && prefersDark),
    );
}

export function setTheme(next: ThemeChoice): void {
    choice.value = next;

    try {
        if (next === defaultChoice) {
            localStorage.removeItem(STORAGE_KEY);
        } else {
            localStorage.setItem(STORAGE_KEY, next);
        }
    } catch {
        // Storage can be blocked. The choice still holds for this visit.
    }

    crossfade(apply);
}

/** The browser fades the whole page across, where it can. */
function crossfade(change: () => void): void {
    const isReduced =
        !useMotion().value || window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if ('startViewTransition' in document && !isReduced) {
        document.startViewTransition(change);
    } else {
        change();
    }
}

/** What the panel allows. A visit to another panel can change it with the layout still mounted. */
export function configureTheme(fallback: ThemeChoice, forced: ThemeChoice | null): void {
    defaultChoice = fallback;
    forcedChoice = forced;

    if (isSynced) {
        choice.value = read();
        apply();
    }
}

/** The viewer's choice. The root view applies it before paint on a full page load. */
export function useThemeChoice() {
    return choice;
}

/** For the layout: it stays on the choice for as long as it's mounted, following the system. */
export function useThemeSync(): void {
    let system: MediaQueryList | undefined;

    onMounted(() => {
        isSynced = true;
        choice.value = read();
        apply();

        system = window.matchMedia('(prefers-color-scheme: dark)');
        system.addEventListener('change', apply);
    });

    onBeforeUnmount(() => {
        isSynced = false;
        system?.removeEventListener('change', apply);
    });
}
