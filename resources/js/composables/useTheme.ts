import { onBeforeUnmount, onMounted, ref } from 'vue';

export type ThemeChoice = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'xq-panel-theme';

const choice = ref<ThemeChoice>('system');

function read(): ThemeChoice {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);

        return saved === 'light' || saved === 'dark' ? saved : 'system';
    } catch {
        return 'system';
    }
}

function apply(): void {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    document.documentElement.classList.toggle(
        'dark',
        choice.value === 'dark' || (choice.value === 'system' && prefersDark),
    );
}

export function setTheme(next: ThemeChoice): void {
    choice.value = next;

    try {
        if (next === 'system') {
            localStorage.removeItem(STORAGE_KEY);
        } else {
            localStorage.setItem(STORAGE_KEY, next);
        }
    } catch {
        // Storage can be blocked. The choice still holds for this visit.
    }

    apply();
}

/** The viewer's choice. The root view applies it before paint on a full page load. */
export function useThemeChoice() {
    return choice;
}

/** For the layout: it stays on the choice for as long as it's mounted, following the system. */
export function useThemeSync(): void {
    let system: MediaQueryList | undefined;

    onMounted(() => {
        choice.value = read();
        apply();

        system = window.matchMedia('(prefers-color-scheme: dark)');
        system.addEventListener('change', apply);
    });

    onBeforeUnmount(() => system?.removeEventListener('change', apply));
}
