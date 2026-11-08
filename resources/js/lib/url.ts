import { router } from '@inertiajs/vue3';

/**
 * Inertia can only visit its own app: another site turns its request away (CORS).
 */
export function isOwnUrl(url: string): boolean {
    return typeof window === 'undefined'
        ? url.startsWith('/')
        : new URL(url, window.location.href).origin === window.location.origin;
}

/** Go to a link from a menu: an Inertia visit inside the app, a full page load outside it. */
export function visitUrl(url: string): void {
    if (isOwnUrl(url)) {
        router.visit(url);
    } else {
        window.location.assign(url);
    }
}
