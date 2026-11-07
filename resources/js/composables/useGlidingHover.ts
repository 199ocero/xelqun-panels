import { computed, onMounted, ref, watch } from 'vue';
import type { ComputedRef, Ref, WatchSource } from 'vue';

/** Where an item sits in its list, counting what's scrolled out of view. */
function topWithin(item: HTMLElement, list: HTMLElement): number {
    return item.getBoundingClientRect().top - list.getBoundingClientRect().top + list.scrollTop;
}

function pillClass(isGliding: boolean, color: string): string[] {
    return [
        'absolute inset-x-0 top-0 rounded-xq-sm pointer-events-none',
        color,
        isGliding
            ? 'transition-[transform,height,opacity] duration-(--xq-duration-base) ease-(--xq-ease-out)'
            : 'transition-opacity duration-(--xq-duration-fast)',
    ];
}

/**
 * The pill behind the current page, which slides there as you move between pages.
 * The layout stays mounted between them, so it has somewhere to slide from.
 */
export function useActivePill(
    list: Ref<HTMLElement | undefined>,
    changes: WatchSource,
): {
    activeClass: ComputedRef<string[]>;
    activeStyle: ComputedRef<Record<string, string | number>>;
} {
    const top = ref(0);
    const height = ref(0);
    const isVisible = ref(false);
    const isGliding = ref(false);

    function measure(): void {
        const item = list.value?.querySelector<HTMLElement>(
            '[data-xq-nav-item][aria-current="page"]',
        );

        isGliding.value = isVisible.value && item !== null && item !== undefined;
        isVisible.value = Boolean(item);

        if (item && list.value) {
            top.value = topWithin(item, list.value);
            height.value = item.offsetHeight;
        }
    }

    onMounted(measure);
    watch(changes, measure, { flush: 'post' });

    return {
        activeClass: computed(() => pillClass(isGliding.value, 'bg-xq-nav-active')),
        activeStyle: computed(() => ({
            transform: `translateY(${top.value}px)`,
            height: `${height.value}px`,
            opacity: isVisible.value ? 1 : 0,
        })),
    };
}

/**
 * One highlight that slides from item to item, passing over the ones between,
 * in place of each item lighting up by itself. The list needs `relative`, and its
 * items `data-xq-nav-item`.
 */
export function useGlidingHover() {
    const top = ref(0);
    const height = ref(0);
    const isVisible = ref(false);
    const isGliding = ref(false);

    function glideTo(event: Event): void {
        const item = (event.target as HTMLElement).closest<HTMLElement>('[data-xq-nav-item]');

        if (!item) {
            return;
        }

        // The first item of a visit appears where it is, rather than sliding in from the last visit.
        isGliding.value = isVisible.value;
        isVisible.value = true;
        top.value = topWithin(item, event.currentTarget as HTMLElement);
        height.value = item.offsetHeight;
    }

    function hideGlide(): void {
        isVisible.value = false;
        isGliding.value = false;
    }

    const glideClass = computed(() => pillClass(isGliding.value, 'bg-xq-nav-hover'));

    const glideStyle = computed(() => ({
        transform: `translateY(${top.value}px)`,
        height: `${height.value}px`,
        opacity: isVisible.value ? 1 : 0,
    }));

    return { glideClass, glideStyle, glideTo, hideGlide };
}
