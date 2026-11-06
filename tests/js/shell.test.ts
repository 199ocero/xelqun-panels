import type { SchemaNode } from '@xelqun/forms';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick, ref } from 'vue';
import XqPanelEntry from '../../resources/js/components/XqPanelEntry.vue';
import XqPanelNewMenu from '../../resources/js/components/XqPanelNewMenu.vue';
import XqPanelSearch from '../../resources/js/components/XqPanelSearch.vue';
import XqPanelSidebar from '../../resources/js/components/XqPanelSidebar.vue';
import XqPanelSidebarCard from '../../resources/js/components/XqPanelSidebarCard.vue';
import { setTheme, useThemeSync } from '../../resources/js/composables/useTheme';
import type { PanelShell } from '../../resources/js/types';

const { reload, visit } = vi.hoisted(() => ({ reload: vi.fn(), visit: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    router: { reload, visit, on: vi.fn(() => vi.fn()) },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

const item = (label: string, overrides = {}) => ({
    label,
    icon: null,
    url: `/${label.toLowerCase()}`,
    active: false,
    badge: null,
    ...overrides,
});

const shell: PanelShell = {
    id: 'admin',
    url: '/admin',
    brand: { name: 'Shop' },
    theme: { primary: null, font: 'Inter' },
    navigation: [
        { label: null, items: [item('Home', { active: true })] },
        { label: 'Catalog', items: [item('Products', { badge: '12' }), item('Categories')] },
    ],
    quickCreate: [],
    searchUrl: '/admin/_search',
    sidebarFooter: null,
    user: { name: 'Ada Lovelace', email: 'ada@example.com' },
    logoutUrl: '/admin/logout',
    profileUrl: '/admin/profile',
};

afterEach(() => {
    visit.mockClear();
    reload.mockClear();
    document.body.innerHTML = '';
});

describe('the sidebar', () => {
    it('marks the current page and names its groups', () => {
        const wrapper = mount(XqPanelSidebar, { props: { panel: shell } });

        expect(wrapper.find('[aria-current="page"]').text()).toBe('Home');
        expect(wrapper.find('[role="group"][aria-label="Catalog"]').text()).toContain('Products');
        expect(wrapper.text()).toContain('12');
    });

    it('keeps labels for screen readers when collapsed, and drops the badges', () => {
        const wrapper = mount(XqPanelSidebar, { props: { panel: shell, collapsed: true } });
        const label = wrapper
            .findAll('[data-xq-sidebar] a span')
            .find((span) => span.text() === 'Products');

        expect(label?.classes()).toContain('sr-only');
        expect(wrapper.text()).not.toContain('12');
    });

    it('shows who is signed in, by initials', () => {
        expect(
            mount(XqPanelSidebar, { props: { panel: shell } })
                .find('[data-xq-user]')
                .text(),
        ).toContain('AL');
    });

    it('leaves the user out for a guest', () => {
        const wrapper = mount(XqPanelSidebar, { props: { panel: { ...shell, user: null } } });

        expect(wrapper.find('[data-xq-user]').exists()).toBe(false);
    });
});

describe('the new button', () => {
    const quickCreate = [{ label: 'Product', icon: null, url: '/products/create' }];

    it('shows only when there is something to create', () => {
        const button = (panel: PanelShell) =>
            mount(XqPanelSidebar, { props: { panel } }).find('[data-xq-new]');

        expect(button(shell).exists()).toBe(false);
        expect(button({ ...shell, quickCreate }).exists()).toBe(true);
    });

    it('keeps its name for screen readers when collapsed', () => {
        const wrapper = mount(XqPanelNewMenu, {
            props: { items: quickCreate, collapsed: true },
        });

        expect(wrapper.find('[data-xq-new]').attributes('aria-label')).toBe('New');
        expect(wrapper.text()).toBe('');
    });
});

describe('an infolist entry', () => {
    const entry = (type: string, cell: object, props: object = {}) =>
        mount(XqPanelEntry, {
            props: {
                node: {
                    key: 'a',
                    type: 'xelqun-panels/entry',
                    hidden: false,
                    props: { column: { label: 'Status', type, props }, cell },
                    children: [],
                } as unknown as SchemaNode,
            },
        });

    it('shows the label and value', () => {
        const text = entry('text-column', { state: 'Live' }).text();

        expect(text).toContain('Status');
        expect(text).toContain('Live');
    });

    it('joins a list', () => {
        expect(entry('text-column', { state: ['Red', 'Blue'] }).text()).toContain('Red, Blue');
    });

    it('gives each value of a badge list its own pill', () => {
        const wrapper = entry('text-column', { state: ['Red', 'Blue'] }, { badge: true });

        expect(wrapper.findAll('span.rounded-full')).toHaveLength(2);
    });

    it('marks an empty value', () => {
        expect(entry('text-column', { state: null }).text()).toContain('—');
    });

    it('links when the cell has a url', () => {
        const wrapper = entry('text-column', { state: 'Live', url: '/live' });

        expect(wrapper.find('a').attributes('href')).toBe('/live');
    });

    it('sizes an image from the column', () => {
        const wrapper = entry('image-column', { state: '/a.png', alt: 'A' }, { size: 64 });

        expect(wrapper.find('img').attributes('width')).toBe('64');
    });
});

describe('the sidebar card', () => {
    const card = {
        title: 'Credits',
        icon: null,
        badge: null,
        value: null,
        note: null,
        progress: 0.5,
        footer: null,
        link: null,
    };

    it('fills the meter to the value and says so to screen readers', () => {
        const wrapper = mount(XqPanelSidebarCard, { props: { card } });

        expect(wrapper.find('[role="progressbar"]').attributes('aria-valuenow')).toBe('50');
        expect(wrapper.findAll('[role="progressbar"] .bg-xq-primary')).toHaveLength(12);
    });

    it('shows an empty meter as empty', () => {
        const wrapper = mount(XqPanelSidebarCard, { props: { card: { ...card, progress: 0 } } });

        expect(wrapper.findAll('[role="progressbar"] .bg-xq-primary')).toHaveLength(0);
    });
});

describe('search', () => {
    function openSearch(searchUrl: string | null = null) {
        const open = ref(true);
        const wrapper = mount(
            defineComponent({
                setup: () => () =>
                    h(XqPanelSearch, {
                        navigation: shell.navigation,
                        searchUrl,
                        open: open.value,
                        'onUpdate:open': (value: boolean) => (open.value = value),
                    }),
            }),
            { attachTo: document.body },
        );

        return {
            open,
            wrapper,
            input: document.querySelector('[data-xq-search] input') as HTMLInputElement,
        };
    }

    async function openedInput(): Promise<HTMLInputElement> {
        await flushPromises();

        return document.querySelector('[data-xq-search] input') as HTMLInputElement;
    }

    async function type(input: HTMLInputElement, text: string): Promise<void> {
        input.value = text;
        input.dispatchEvent(new Event('input'));
        await nextTick();
    }

    function press(input: HTMLInputElement, key: string): void {
        input.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }));
    }

    function labels(): string[] {
        return [...document.querySelectorAll('[data-xq-search] li')].map((li) =>
            li.textContent!.trim(),
        );
    }

    it('finds pages by name, ignoring case', async () => {
        openSearch();
        const input = await openedInput();

        await type(input, 'CAT');

        expect(labels()).toEqual(['Categories']);
    });

    it('tells apart links with the same label', async () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        const open = ref(true);

        mount(XqPanelSearch, {
            props: {
                open: open.value,
                searchUrl: null,
                navigation: [
                    { label: 'Sales', items: [item('Settings', { url: '/sales/settings' })] },
                    { label: 'Team', items: [item('Settings', { url: '/team/settings' })] },
                ],
            },
            attachTo: document.body,
        });
        await flushPromises();

        expect(labels()).toEqual(['Settings', 'Settings']);
        expect(warn).not.toHaveBeenCalled();
        warn.mockRestore();
    });

    it('says when nothing matches', async () => {
        openSearch();
        const input = await openedInput();

        await type(input, 'zzz');

        expect(document.querySelector('[data-xq-search]')!.textContent).toContain('Nothing found.');
    });

    it('goes to the first result on Enter, and closes', async () => {
        const { open } = openSearch();
        const input = await openedInput();

        await type(input, 'prod');
        press(input, 'Enter');

        expect(visit).toHaveBeenCalledWith('/products');
        expect(open.value).toBe(false);
    });

    it('moves through results with the arrows, wrapping at the ends', async () => {
        openSearch();
        const input = await openedInput();

        press(input, 'ArrowUp');
        await nextTick();
        press(input, 'Enter');

        expect(visit).toHaveBeenCalledWith('/categories');
    });
});

describe('record search', () => {
    const found = {
        groups: [
            {
                label: 'Orders',
                icon: null,
                results: [{ title: 'Order 1001', url: '/orders/1', details: 'Status: paid' }],
            },
        ],
    };

    function respondWith(status: number, body: unknown = {}): ReturnType<typeof vi.fn> {
        const fetchMock = vi.fn(async () => new Response(JSON.stringify(body), { status }));

        vi.stubGlobal('fetch', fetchMock);

        return fetchMock;
    }

    async function search(text: string, searchUrl: string | null = '/admin/_search') {
        const open = ref(true);

        mount(
            defineComponent({
                setup: () => () =>
                    h(XqPanelSearch, {
                        navigation: shell.navigation,
                        searchUrl,
                        open: open.value,
                        'onUpdate:open': (value: boolean) => (open.value = value),
                    }),
            }),
            { attachTo: document.body },
        );
        await flushPromises();

        const input = document.querySelector('[data-xq-search] input') as HTMLInputElement;

        await typeInto(input, text);

        return { input, open };
    }

    async function typeInto(input: HTMLInputElement, text: string): Promise<void> {
        input.value = text;
        input.dispatchEvent(new Event('input'));
        await nextTick();
    }

    async function settle(): Promise<void> {
        await vi.advanceTimersByTimeAsync(250);
        await flushPromises();
    }

    function palette(): string {
        return document.querySelector('[data-xq-search]')!.textContent!;
    }

    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('shows matching records under their resource, and opens one', async () => {
        const fetchMock = respondWith(200, found);
        const { input, open } = await search('order');

        await settle();

        expect(palette()).toContain('Orders');
        expect(palette()).toContain('Status: paid');
        expect(fetchMock.mock.calls[0][1].body).toBe('{"q":"order"}');

        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));

        expect(visit).toHaveBeenCalledWith('/orders/1');
        expect(open.value).toBe(false);
    });

    it('waits for a pause in typing, and ignores too little', async () => {
        const fetchMock = respondWith(200, found);
        const { input } = await search('o');

        await settle();
        expect(fetchMock).not.toHaveBeenCalled();

        await typeInto(input, 'or');
        await typeInto(input, 'ord');
        await settle();

        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('never asks without a search url', async () => {
        const fetchMock = respondWith(200, found);

        await search('order', null);
        await settle();

        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('drops old matches as soon as the text changes', async () => {
        respondWith(200, found);
        const { input } = await search('order');

        await settle();
        await typeInto(input, 'orders of');

        expect(palette()).not.toContain('Order 1001');
    });

    it('ignores an answer to text that has since changed', async () => {
        let answer = (_response: Response) => {};
        let signal: AbortSignal | undefined;

        vi.stubGlobal(
            'fetch',
            vi.fn(
                (_url: string, init: RequestInit) =>
                    new Promise((resolve, reject) => {
                        signal = init.signal!;
                        signal.addEventListener('abort', () =>
                            reject(new DOMException('Aborted', 'AbortError')),
                        );
                        answer = resolve;
                    }),
            ),
        );

        const { input } = await search('order');

        await settle();
        await typeInto(input, 'zzz');
        answer(new Response(JSON.stringify(found)));
        await flushPromises();

        expect(signal!.aborted).toBe(true);
        expect(palette()).not.toContain('Order 1001');
        expect(palette()).not.toContain('search right now');
    });

    it("keeps page matches and says so when records can't be searched", async () => {
        respondWith(429);
        await search('prod');

        await settle();

        expect(palette()).toContain('Products');
        expect(palette()).toContain("Records can't be searched right now.");
    });

    it.each([401, 403, 419])('reloads into the right page on a %i', async (status) => {
        respondWith(status);
        const { open } = await search('order');

        await settle();

        expect(reload).toHaveBeenCalled();
        expect(open.value).toBe(false);
    });
});

describe('the theme', () => {
    const Layout = defineComponent({
        setup() {
            useThemeSync();

            return () => h('div');
        },
    });

    beforeEach(() => {
        localStorage.clear();
        document.documentElement.classList.remove('dark');
        window.matchMedia = vi.fn(() => ({
            matches: false,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        })) as unknown as typeof window.matchMedia;
    });

    it('applies a saved choice when the layout mounts', () => {
        localStorage.setItem('xq-panel-theme', 'dark');

        mount(Layout);

        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('forgets the choice when going back to the system', () => {
        setTheme('dark');
        setTheme('system');

        expect(localStorage.getItem('xq-panel-theme')).toBeNull();
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('still switches after leaving the panel and coming back', () => {
        mount(Layout).unmount();
        mount(Layout);

        setTheme('dark');

        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });
});
