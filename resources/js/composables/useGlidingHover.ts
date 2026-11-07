import { computed, ref } from 'vue';

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
        const list = event.currentTarget as HTMLElement;

        if (!item) {
            return;
        }

        // The first item of a visit appears where it is, rather than sliding in from the last visit.
        isGliding.value = isVisible.value;
        isVisible.value = true;
        top.value =
            item.getBoundingClientRect().top - list.getBoundingClientRect().top + list.scrollTop;
        height.value = item.offsetHeight;
    }

    function hideGlide(): void {
        isVisible.value = false;
        isGliding.value = false;
    }

    const glideClass = computed(() => [
        'absolute inset-x-0 top-0 rounded-xq-sm bg-xq-nav-hover pointer-events-none motion-reduce:transition-none',
        isGliding.value
            ? 'transition-[transform,height,opacity] duration-200 ease-out'
            : 'transition-opacity duration-150',
    ]);

    const glideStyle = computed(() => ({
        transform: `translateY(${top.value}px)`,
        height: `${height.value}px`,
        opacity: isVisible.value ? 1 : 0,
    }));

    return { glideClass, glideStyle, glideTo, hideGlide };
}
