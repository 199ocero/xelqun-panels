import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import XqPanelPage from '../../resources/js/pages/XqPanelPage.vue';
import type { PanelContent, PanelPageData } from '../../resources/js/types';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<i />' },
    router: { on: vi.fn(() => vi.fn()) },
}));

const XqActions = { props: ['actions'], template: '<b data-actions>{{ actions.id }}</b>' };
const XqTable = { props: ['table'], template: '<u data-table>{{ table.id }}</u>' };

function render(page: Partial<PanelPageData>) {
    return mount(XqPanelPage, {
        props: {
            page: {
                title: 'Home',
                eyebrow: null,
                subheading: null,
                breadcrumbs: [],
                headerActions: null,
                content: [],
                ...page,
            },
        },
        global: { stubs: { Head: true, XqActions, XqTable } },
    });
}

describe('XqPanelPage', () => {
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
