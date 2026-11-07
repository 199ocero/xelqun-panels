import { ref } from 'vue';

const STORAGE_KEY = 'xq-panel-motion';

const isMotionOn = ref(true);

let isOnByDefault = true;

function read(): boolean {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);

        return saved === null ? isOnByDefault : saved === 'on';
    } catch {
        return isOnByDefault;
    }
}

function apply(): void {
    document.documentElement.toggleAttribute('data-xq-motion-off', !isMotionOn.value);
}

/** The panel's default, which holds until someone chooses. */
export function configureMotion(isOn: boolean): void {
    isOnByDefault = isOn;
    isMotionOn.value = read();
    apply();
}

export function setMotion(isOn: boolean): void {
    isMotionOn.value = isOn;

    try {
        localStorage.setItem(STORAGE_KEY, isOn ? 'on' : 'off');
    } catch {
        // Storage can be blocked. The choice still holds for this visit.
    }

    apply();
}

/** Off for this panel only, so a page outside it keeps its own motion. */
export function clearMotion(): void {
    document.documentElement.removeAttribute('data-xq-motion-off');
}

export function useMotion() {
    return isMotionOn;
}
