import { computed, ref } from 'vue';

/** A color someone can pick. Grays are for secondary, the rest for primary. */
export type PaletteColor = { name: string; hex: string; fill: string; text: string; gray: boolean };

export type AppearanceSwitcher = { presets: string[]; palette: PaletteColor[] };

export type Appearance = { preset?: string; primary?: string; secondary?: string };

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
    apply();
}

export function setAppearance(change: Appearance): void {
    appearance.value = { ...appearance.value, ...change };

    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(appearance.value));
    } catch {
        // Storage can be blocked. The choice still holds for this visit.
    }

    apply();
}

export function resetAppearance(): void {
    appearance.value = {};

    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch {
        // Nothing was saved.
    }

    apply();
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
