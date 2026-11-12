import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    configureAppearance,
    currentPreset,
    resetAppearance,
    setAppearance,
} from '../../resources/js/composables/useAppearance';

const { postJson } = vi.hoisted(() => ({ postJson: vi.fn(() => Promise.resolve(null)) }));

vi.mock('@xelqun/forms/internal', async (original) => ({
    ...(await original<object>()),
    postJson,
}));

const switcher = {
    presets: ['default', 'soft', 'sharp', 'mono'],
    palette: [
        { name: 'Stone', hex: '#57534e', fill: '#57534e', text: '#ffffff', gray: true },
        { name: 'Violet', hex: '#7c3aed', fill: '#7c3aed', text: '#ffffff', gray: false },
    ],
    saved: null,
    saveUrl: null,
};

const panelColors = { '--xq-color-primary': '#0071e3', '--xq-color-gray': '#52525b' };

function css(name: string): string {
    return document.documentElement.style.getPropertyValue(name);
}

describe('appearance', () => {
    beforeEach(() => {
        postJson.mockClear();
        localStorage.clear();
        configureAppearance('soft', switcher, panelColors);
    });

    it('puts a picked color and gray over the panel own, and reset brings them back', () => {
        setAppearance({ preset: 'sharp', primary: 'Violet', secondary: 'Stone' });

        expect(document.documentElement.getAttribute('data-xq-preset')).toBe('sharp');
        expect(css('--xq-color-primary')).toBe('#7c3aed');
        expect(css('--xq-color-secondary')).toBe('#57534e');
        expect(css('--xq-color-gray')).toBe('#57534e');

        resetAppearance();

        expect(document.documentElement.getAttribute('data-xq-preset')).toBe('soft');
        expect(css('--xq-color-primary')).toBe('#0071e3');
        expect(css('--xq-color-gray')).toBe('#52525b');
        expect(css('--xq-color-secondary')).toBe('');
    });

    it('ignores a gray as primary, a color as secondary and a style the panel does not offer', () => {
        setAppearance({ preset: 'retro', primary: 'Stone', secondary: 'Violet' });

        expect(currentPreset.value).toBe('soft');
        expect(document.documentElement.getAttribute('data-xq-preset')).toBe('soft');
        expect(css('--xq-color-primary')).toBe('#0071e3');
        expect(css('--xq-color-secondary')).toBe('');
    });

    it('applies nothing someone picked when the panel has no switcher', () => {
        setAppearance({ primary: 'Violet' });
        configureAppearance('default', null, panelColors);

        expect(css('--xq-color-primary')).toBe('#0071e3');
        expect(document.documentElement.getAttribute('data-xq-preset')).toBe('default');
    });

    it('takes the account copy on a page load, and saves each pick back to it', () => {
        localStorage.setItem('xq-panel-appearance', JSON.stringify({ preset: 'mono' }));
        configureAppearance(
            'soft',
            { ...switcher, saved: { preset: 'sharp' }, saveUrl: '/admin/_appearance' },
            panelColors,
        );

        expect(currentPreset.value).toBe('sharp');
        expect(JSON.parse(localStorage.getItem('xq-panel-appearance')!)).toEqual({
            preset: 'sharp',
        });

        setAppearance({ primary: 'Violet' });

        expect(postJson).toHaveBeenCalledWith('/admin/_appearance', {
            preset: 'sharp',
            primary: 'Violet',
        });

        resetAppearance();

        expect(postJson).toHaveBeenLastCalledWith('/admin/_appearance', {});
    });
});
