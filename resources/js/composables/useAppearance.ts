import { postJson } from '@xelqun/forms/internal';
import { computed, ref } from 'vue';

/** A color someone can pick. Grays are for secondary, the rest for primary. */
export type PaletteColor = { name: string; hex: string; fill: string; text: string; gray: boolean };

export type Appearance = { preset?: string; primary?: string; secondary?: string };

export type AppearanceSwitcher = {
    presets: string[];
    palette: PaletteColor[];
    /** What the account keeps, when the user model does. An empty list means nothing yet. */
    saved: Appearance | [] | null;
    saveUrl: string | null;
};

const STORAGE_KEY = 'xq-panel-appearance';

/** What a pick sets on <html>, over the panel's own colors. */
const PROPERTIES = [
    '--xq-color-primary',
    '--xq-color-primary-fill',
    '--xq-color-primary-foreground',
    '--xq-color-secondary',
    '--xq-color-gray',
];

const switcher = ref<AppearanceSwitcher | null>(null);
const appearance = ref<Appearance>({});

const panelPreset = ref('default');
let panelColors: Record<string, string> = {};

/** The account's copy is read once per page load. After that this tab's own picks lead, so a visit can't undo one that's still saving. */
let hasReadAccount = false;

function read(): Appearance {
    try {
        const saved: unknown = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}');

        return saved !== null && typeof saved === 'object' ? (saved as Appearance) : {};
    } catch {
        return {};
    }
}

/** The style to show: someone's pick when the panel offers it, otherwise the panel's own. */
function shownPreset(): string {
    const picked = appearance.value.preset;

    return picked && switcher.value?.presets.includes(picked) ? picked : panelPreset.value;
}

function apply(): void {
    const root = document.documentElement;
    const options = switcher.value;
    const choice = options ? appearance.value : {};
    const primary = options?.palette.find((color) => !color.gray && color.name === choice.primary);
    const secondary = options?.palette.find(
        (color) => color.gray && color.name === choice.secondary,
    );
    const picked: Record<string, string> = {
        ...panelColors,
        ...(primary && {
            '--xq-color-primary': primary.hex,
            '--xq-color-primary-fill': primary.fill,
            '--xq-color-primary-foreground': primary.text,
        }),
        ...(secondary && {
            '--xq-color-secondary': secondary.hex,
            '--xq-color-gray': secondary.hex,
        }),
    };

    root.setAttribute('data-xq-preset', shownPreset());

    PROPERTIES.forEach((name) =>
        picked[name] ? root.style.setProperty(name, picked[name]) : root.style.removeProperty(name),
    );
}

/** The panel's style and colors, then whatever someone picked over them. */
export function configureAppearance(
    preset: string,
    options: AppearanceSwitcher | null,
    colors: Record<string, string>,
): void {
    panelPreset.value = preset;
    panelColors = colors;
    switcher.value = options;
    appearance.value = read();

    const saved = options?.saved;

    if (!hasReadAccount && saved && !Array.isArray(saved) && Object.keys(saved).length > 0) {
        hasReadAccount = true;
        remember(saved);
    }

    apply();
}

function remember(next: Appearance): void {
    appearance.value = next;

    try {
        if (Object.keys(next).length > 0) {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
        } else {
            localStorage.removeItem(STORAGE_KEY);
        }
    } catch {
        // Storage can be blocked. The choice still holds for this visit.
    }
}

/** Keeps it on the account too. A failed save leaves the choice in this browser. */
function save(): void {
    const url = switcher.value?.saveUrl;

    if (url) {
        postJson(url, appearance.value).catch(() => {});
    }
}

export function setAppearance(change: Appearance): void {
    remember({ ...appearance.value, ...change });
    apply();
    save();
}

export function resetAppearance(): void {
    remember({});
    apply();
    save();
}

/** Null when the panel doesn't offer a switcher. */
export function useAppearanceSwitcher() {
    return switcher;
}

export function useAppearance() {
    return appearance;
}

/** The style showing now: someone's pick, or the panel's own. */
export const currentPreset = computed(shownPreset);
