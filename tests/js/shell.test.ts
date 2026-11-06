import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick, ref } from 'vue';
import XqPanelSearch from '../../resources/js/components/XqPanelSearch.vue';
import XqPanelSidebar from '../../resources/js/components/XqPanelSidebar.vue';
import XqPanelSidebarCard from '../../resources/js/components/XqPanelSidebarCard.vue';
import { setTheme, useThemeSync } from '../../resources/js/composables/useTheme';
import type { PanelShell } from '../../resources/js/types';

const { visit } = vi.hoisted(() => ({ visit: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    router: { visit, on: vi.fn(() => vi.fn()) },
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
    sidebarFooter: null,
    user: { name: 'Ada Lovelace', email: 'ada@example.com' },
    logoutUrl: '/admin/logout',
};

afterEach(() => {
    visit.mockClear();
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
    function openSearch() {
        const open = ref(true);
        const wrapper = mount(
            defineComponent({
                setup: () => () =>
                    h(XqPanelSearch, {
                        navigation: shell.navigation,
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
