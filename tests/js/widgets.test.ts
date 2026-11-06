import { flushPromises, mount } from '@vue/test-utils';
import { createXelqunForms } from '@xelqun/forms';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import XqPanelChart from '../../resources/js/components/widgets/XqPanelChart.vue';
import XqPanelListWidget from '../../resources/js/components/widgets/XqPanelListWidget.vue';
import XqPanelStats from '../../resources/js/components/widgets/XqPanelStats.vue';
import XqPanelTiles from '../../resources/js/components/widgets/XqPanelTiles.vue';
import XqPanelWidgets from '../../resources/js/components/widgets/XqPanelWidgets.vue';
import type { PanelChart, PanelWidget } from '../../resources/js/types';

const { reload } = vi.hoisted(() => ({ reload: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    router: { reload, replace: vi.fn(), on: vi.fn(() => vi.fn()) },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

function row(key: string, cells: Record<string, unknown> = {}, extra: object = {}) {
    const state = {
        title: `Row ${key}`,
        meta: null,
        icon: null,
        iconColor: 'gray',
        progress: null,
        progressNote: null,
        ...cells,
    };

    return {
        key,
        actions: [],
        cells: Object.fromEntries(
            Object.entries(state).map(([name, value]) => [name, { state: value }]),
        ),
        ...extra,
    };
}

function table(rows: unknown[], total = rows.length) {
    return {
        $xq: 1,
        pkg: '0.4.0',
        id: 'list',
        token: 'token',
        endpoints: { query: '/query', action: '/action' },
        headerActions: [],
        bulkActions: [],
        maxSelectable: 1000,
        searchable: false,
        filters: null,
        filterDefaults: {},
        query: {},
        sort: null,
        activeFilters: 0,
        columns: [],
        rows,
        pagination: { page: 1, perPage: 5, total, lastPage: 1, options: [5] },
    } as never;
}

function listWidget(rows: unknown[], total?: number) {
    return mount(XqPanelListWidget, {
        props: { table: table(rows, total), heading: 'Approvals', seeAllUrl: '/all' },
    });
}

afterEach(() => vi.clearAllMocks());

describe('stats', () => {
    const stat = {
        label: 'Orders',
        value: '24',
        description: 'Up',
        icon: null,
        chart: [] as number[],
    };

    it('shows the number and its note', () => {
        const wrapper = mount(XqPanelStats, { props: { stats: [stat] } });

        expect(wrapper.text()).toContain('24');
        expect(wrapper.text()).toContain('Up');
    });

    it('draws a line only when there is one', () => {
        expect(
            mount(XqPanelStats, { props: { stats: [stat] } })
                .find('svg')
                .exists(),
        ).toBe(false);

        const wrapper = mount(XqPanelStats, { props: { stats: [{ ...stat, chart: [1, 3] }] } });

        expect(wrapper.find('polyline').attributes('points')).toBe('0.0,32.0 96.0,0.0');
    });

    it('draws a flat series across the middle, not as nonsense', () => {
        const wrapper = mount(XqPanelStats, { props: { stats: [{ ...stat, chart: [5, 5] }] } });

        expect(wrapper.find('polyline').attributes('points')).toBe('0.0,16.0 96.0,16.0');
    });
});

describe('the list widget', () => {
    it('shows the total, not just the rows on show', () => {
        const wrapper = listWidget([row('1')], 12);

        expect(wrapper.find('header').text()).toContain('12');
        expect(wrapper.find('header a').attributes('href')).toBe('/all');
    });

    it("shows a row's title and detail", () => {
        const wrapper = listWidget([row('1', { meta: 'By Ada' })]);

        expect(wrapper.find('[data-xq-list-row]').text()).toContain('Row 1');
        expect(wrapper.find('[data-xq-list-row]').text()).toContain('By Ada');
    });

    it('shows progress, and says so to screen readers', () => {
        const wrapper = listWidget([
            row('1', { progress: 0.25, progressNote: 'About 2 min left' }),
        ]);

        expect(wrapper.find('[role="progressbar"]').attributes('aria-valuenow')).toBe('25');
        expect(wrapper.text()).toContain('About 2 min left');
    });

    it('treats no progress differently from none yet', () => {
        expect(
            listWidget([row('1')])
                .find('[role="progressbar"]')
                .exists(),
        ).toBe(false);
        expect(
            listWidget([row('1', { progress: 0 })])
                .find('[role="progressbar"]')
                .exists(),
        ).toBe(true);
    });

    it('links a row, with a chevron when nothing else is on the right', () => {
        const wrapper = listWidget([row('1', {}, { url: '/posts/1' })]);

        expect(wrapper.find('[data-xq-list-row] a').attributes('href')).toBe('/posts/1');
        expect(wrapper.find('[data-xq-list-row] svg').exists()).toBe(true);
    });

    it('refreshes the rest of the dashboard after a row action', async () => {
        const post = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
            new Response(JSON.stringify({ result: null, redirect: null, notifications: [] }), {
                status: 200,
                headers: { 'content-type': 'application/json' },
            }),
        );
        const action = { name: 'approve', props: { label: 'Approve', style: 'button' } };
        const wrapper = listWidget([row('1', {}, { actions: [action] })]);

        await wrapper.find('[data-xq-list-row] button').trigger('click');
        await flushPromises();

        expect(reload).toHaveBeenCalledWith({ only: ['widgets', 'page'] });
        post.mockRestore();
    });

    it('shows an empty list as empty', () => {
        expect(listWidget([]).text()).toContain('Nothing here.');
    });
});

describe('tiles', () => {
    it('shows a status, and only links a tile with a url', () => {
        const wrapper = mount(XqPanelTiles, {
            props: {
                heading: 'Agents',
                seeAllUrl: null,
                tiles: [
                    { label: 'Fennel', icon: null, status: 'Working', color: 'primary', url: '/a' },
                    { label: 'Tilt', icon: null, status: null, color: 'gray', url: null },
                ],
            },
        });

        expect(wrapper.find('header').text()).toContain('2');
        expect(wrapper.findAll('[data-xq-tiles] a').map((a) => a.text())).toEqual([
            'Fennel Working',
        ]);
    });
});

describe('the widget grid', () => {
    it('gives each widget its width', () => {
        const widgets = [
            {
                id: 'a',
                type: 'stats',
                columnSpan: 8,
                data: { heading: null, seeAllUrl: null, stats: [] },
            },
        ] as PanelWidget[];
        const wrapper = mount(XqPanelWidgets, { props: { widgets } });

        expect(wrapper.find('[data-xq-widget="a"]').attributes('style')).toContain(
            '--xq-widget-span: 8',
        );
    });

    it("renders a widget of the app's own, from the registry", async () => {
        const Hello = defineComponent({
            props: ['greeting'],
            setup: (props) => () => h('i', { 'data-hello': true }, props.greeting),
        });
        const widgets = [
            {
                id: 'a',
                type: 'app/hello',
                columnSpan: 12,
                data: { heading: null, seeAllUrl: null, greeting: 'Hi' },
            },
        ] as PanelWidget[];
        const wrapper = mount(XqPanelWidgets, {
            props: { widgets },
            global: {
                plugins: [
                    createXelqunForms({
                        plugins: [{ id: 'app/hello', components: { 'app/hello': Hello } }],
                    }),
                ],
            },
        });

        await flushPromises();

        expect(wrapper.find('[data-hello]').text()).toBe('Hi');
    });
});

describe('XqPanelChart', () => {
    const chart = (overrides: Partial<PanelChart> = {}): PanelChart => ({
        type: 'line',
        labels: ['Jan', 'Feb', 'Mar'],
        datasets: [{ label: 'Orders', data: [10, 40, 25] }],
        ...overrides,
    });

    it('draws one line per series, with no legend for a single one', () => {
        const wrapper = mount(XqPanelChart, { props: { chart: chart() } });

        expect(wrapper.findAll('[data-xq-chart-line]')).toHaveLength(1);
        expect(wrapper.find('[data-xq-chart-legend]').exists()).toBe(false);
    });

    it('names every series once there are two', () => {
        const wrapper = mount(XqPanelChart, {
            props: {
                chart: chart({
                    datasets: [
                        { label: 'Orders', data: [1, 2, 3] },
                        { label: 'Returns', data: [0, 1, 0] },
                    ],
                }),
            },
        });

        expect(wrapper.find('[data-xq-chart-legend]').text()).toContain('Returns');
    });

    it('draws a bar for each value, no wider than 24px', () => {
        const wrapper = mount(XqPanelChart, { props: { chart: chart({ type: 'bar' }) } });
        const bars = wrapper.findAll('[data-xq-chart-bar]');

        expect(bars).toHaveLength(3);
        expect(Number(bars[0].attributes('width'))).toBeLessThanOrEqual(24);
    });

    it('keeps a bar on the baseline for a negative value', () => {
        const wrapper = mount(XqPanelChart, {
            props: {
                chart: chart({ type: 'bar', datasets: [{ label: 'Net', data: [10, -10, 0] }] }),
            },
        });
        const heights = wrapper
            .findAll('[data-xq-chart-bar]')
            .map((bar) => Number(bar.attributes('height')));

        expect(heights[0]).toBeCloseTo(heights[1]);
        expect(heights[2]).toBe(0);
    });

    it('puts gridlines on round numbers', () => {
        const wrapper = mount(XqPanelChart, {
            props: { chart: chart({ datasets: [{ label: 'Orders', data: [5, 25, 12] }] }) },
        });
        const labels = wrapper.findAll('svg g text').map((label) => label.text());

        expect(labels).toEqual(['0', '10', '20', '30']);
    });

    it('draws a flat zero series without dividing by zero', () => {
        const wrapper = mount(XqPanelChart, {
            props: { chart: chart({ datasets: [{ label: 'Orders', data: [0, 0, 0] }] }) },
        });

        expect(wrapper.find('[data-xq-chart-line]').attributes('points')).not.toContain('NaN');
    });

    it('shows a slice per label, and the total in the middle', () => {
        const wrapper = mount(XqPanelChart, { props: { chart: chart({ type: 'doughnut' }) } });

        expect(wrapper.findAll('[data-xq-chart-slice]')).toHaveLength(3);
        expect(wrapper.find('svg text').text()).toBe('75');
    });

    it('explains a point on hover', async () => {
        const wrapper = mount(XqPanelChart, { props: { chart: chart() } });

        await wrapper.findAll('[data-xq-chart-target]')[1].trigger('mouseenter');

        expect(wrapper.find('[data-xq-chart-tooltip]').text()).toContain('Feb');
        expect(wrapper.find('[data-xq-chart-tooltip]').text()).toContain('40');
    });

    it('leaves a table for screen readers', () => {
        const wrapper = mount(XqPanelChart, { props: { chart: chart() } });

        expect(wrapper.find('table.sr-only').text()).toContain('Feb');
    });
});
