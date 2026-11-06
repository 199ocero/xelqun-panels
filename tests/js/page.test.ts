import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import XqPanelPage from '../../resources/js/pages/XqPanelPage.vue';
import type { PanelContent, PanelPageData } from '../../resources/js/types';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<i />' },
    router: { on: vi.fn(() => vi.fn()) },
}));

const XqActions = { props: ['actions'], template: '<b data-actions>{{ actions.id }}</b>' };
const XqForm = {
    props: ['form', 'action', 'method', 'submitLabel'],
    template: '<form data-form :action="action" :data-method="method">{{ submitLabel }}</form>',
};
const XqTable = { props: ['table'], template: '<u data-table>{{ table.id }}</u>' };

function render(page: Partial<PanelPageData>) {
    return mount(XqPanelPage, {
        props: {
            page: {
                title: 'Home',
                eyebrow: null,
                subheading: null,
                breadcrumbs: [],
                hasWidgets: false,
                headerActions: null,
                content: [],
                ...page,
            },
        },
        global: { stubs: { Head: true, XqActions, XqForm, XqTable } },
    });
}

describe('XqPanelPage', () => {
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
