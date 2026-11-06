import type { XelqunPlugin } from '@xelqun/forms';

/** Goes in the `plugins` of `createXelqunForms()`. */
export function panelsPlugin(): XelqunPlugin {
    return { id: 'xelqun/panels' };
}
