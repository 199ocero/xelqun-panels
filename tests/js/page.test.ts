import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import XqPanelPage from '../../resources/js/pages/XqPanelPage.vue';
import type { PanelContent, PanelPageData, PanelWidget } from '../../resources/js/types';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<i />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: { on: vi.fn(() => vi.fn()) },
    usePage: () => ({ props: { xelqunPanel: { hooks: {} } } }),
}));

const XqActions = { props: ['actions'], template: '<b data-actions>{{ actions.id }}</b>' };
const XqForm = {
    props: ['form', 'action', 'method', 'submitLabel'],
    template: '<form data-form :action="action" :data-method="method">{{ submitLabel }}</form>',
};
const XqTable = { props: ['table'], template: '<u data-table>{{ table.id }}</u>' };

const XqPanelWidgets = {
    props: ['widgets'],
    template: '<div data-widgets>{{ widgets.length }}</div>',
};

function render(page: Partial<PanelPageData>, widgets?: PanelWidget[]) {
    return mount(XqPanelPage, {
        props: {
            widgets,
            page: {
                title: 'Home',
                eyebrow: null,
                subheading: null,
                breadcrumbs: [],
                hasWidgets: false,
                tabs: [],
                subNavigation: [],
                notice: null,
                headerActions: null,
                content: [],
                ...page,
            },
        },
        global: { stubs: { Head: true, XqActions, XqForm, XqTable, XqPanelWidgets } },
    });
}

describe('XqPanelPage', () => {
    it('keeps the widgets on screen while a reload brings new ones', async () => {
        const widgets = [{ id: 'one' }, { id: 'two' }] as unknown as PanelWidget[];
        const wrapper = render({ hasWidgets: true });

        expect(wrapper.find('[data-xq-widgets-loading]').exists()).toBe(true);

        await wrapper.setProps({ widgets });
        expect(wrapper.find('[data-widgets]').text()).toBe('2');

        await wrapper.setProps({ widgets: undefined });
        expect(wrapper.find('[data-widgets]').text()).toBe('2');
        expect(wrapper.find('[data-xq-widgets-loading]').exists()).toBe(false);

        await wrapper.setProps({ widgets: widgets.slice(1) });
        expect(wrapper.find('[data-widgets]').text()).toBe('1');
    });

    it('marks the open tab and shows its count', () => {
        const tab = { icon: null, badge: null, url: '/posts', active: false };
        const wrapper = render({
            tabs: [
                { ...tab, key: 'all', label: 'All', active: true },
                { ...tab, key: 'live', label: 'Live', badge: '3', url: '/posts?tab=live' },
            ],
        });

        expect(wrapper.find('[data-xq-tabs] [aria-current="page"]').text()).toBe('All');
        expect(wrapper.find('[data-xq-tabs]').text()).toContain('3');
    });

    it('links between the members of a cluster', () => {
        const wrapper = render({
            subNavigation: [
                {
                    key: 'Colors',
                    label: 'Colors',
                    icon: null,
                    badge: null,
                    url: '/c',
                    active: true,
                },
            ],
        });

        expect(wrapper.find('[data-xq-tabs] [aria-current="page"]').text()).toBe('Colors');
    });

    it('shows a notice, such as recovery codes', () => {
        const wrapper = render({
            notice: { title: 'Save these', description: 'Once only', lines: ['aaa', 'bbb'] },
        });

        expect(wrapper.findAll('[data-xq-notice] li').map((line) => line.text())).toEqual([
            'aaa',
            'bbb',
        ]);
    });

    it('puts several relation tables on tabs, one at a time', async () => {
        const wrapper = render({
            content: [
                { type: 'table', schema: { id: 'items' }, heading: 'Items' },
                { type: 'table', schema: { id: 'tags' }, heading: 'Tags' },
            ] as unknown as PanelContent[],
        });
        const sections = wrapper.findAll('section');

        expect(sections[0].isVisible()).toBe(true);
        expect(sections[1].attributes('style')).toContain('display: none');

        await sections[0].findAll('[role="tab"]')[1].trigger('click');

        expect(sections[0].attributes('style')).toContain('display: none');
        expect(sections[1].attributes('style') ?? '').not.toContain('display: none');
    });

    it('names a relation table with a heading', () => {
        const wrapper = render({
            content: [
                { type: 'table', schema: { id: 'comments' }, heading: 'Comments' },
            ] as unknown as PanelContent[],
        });

        expect(wrapper.find('h2').text()).toBe('Comments');
    });

    it('renders a form with where it submits', () => {
        const content = [
            {
                type: 'form',
                schema: { id: 'profile' },
                action: '/profile',
                method: 'put',
                submitLabel: 'Save',
                revision: 'a',
            },
        ] as PanelContent[];
        const form = render({ content }).find('[data-form]');

        expect(form.attributes('action')).toBe('/profile');
        expect(form.attributes('data-method')).toBe('put');
        expect(form.text()).toBe('Save');
    });

    it('shows its title as the only top-level heading', () => {
        expect(
            render({ title: 'Reports' })
                .findAll('h1')
                .map((h) => h.text()),
        ).toEqual(['Reports']);
    });

    it('shows header actions only when there are some', () => {
        expect(render({}).find('[data-actions]').exists()).toBe(false);

        const headerActions = { id: 'home-actions' } as PanelPageData['headerActions'];

        expect(render({ headerActions }).find('[data-actions]').text()).toBe('home-actions');
    });

    it('renders each kind of content, in order', () => {
        const content = [
            { type: 'actions', schema: { id: 'first' } },
            { type: 'table', schema: { id: 'second' } },
        ] as PanelContent[];
        const items = render({ content }).findAll('[data-actions], [data-table]');

        expect(items.map((item) => [item.element.tagName, item.text()])).toEqual([
            ['B', 'first'],
            ['U', 'second'],
        ]);
    });
});
