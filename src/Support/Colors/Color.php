<?php

namespace Xelqun\Panels\Support\Colors;

/**
 * Tailwind's palette, at its 600 shade, for Panel::colors(), as in
 * ['primary' => Color::Amber].
 *
 * @api
 */
enum Color: string
{
    case Slate = '#475569';
    case Gray = '#4b5563';
    case Zinc = '#52525b';
    case Neutral = '#525252';
    case Stone = '#57534e';
    case Taupe = '#5b4f4b';
    case Mauve = '#594c5b';
    case Mist = '#4b585b';
    case Olive = '#5b5b4b';
    case Red = '#dc2626';
    case Orange = '#ea580c';
    case Amber = '#d97706';
    case Yellow = '#ca8a04';
    case Lime = '#65a30d';
    case Green = '#16a34a';
    case Emerald = '#059669';
    case Teal = '#0d9488';
    case Cyan = '#0891b2';
    case Sky = '#0284c7';
    case Blue = '#2563eb';
    case Indigo = '#4f46e5';
    case Violet = '#7c3aed';
    case Purple = '#9333ea';
    case Fuchsia = '#c026d3';
    case Pink = '#db2777';
    case Rose = '#e11d48';

    /**
     * Every case by name, for a Select that lets someone choose.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_column(self::cases(), 'name', 'name');
    }

    public static function fromName(?string $name): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Which text reads best on a hex color. One of black and white always clears
     * WCAG AA's 4.5:1, while a softer dark doesn't on a mid-tone color.
     */
    public static function foregroundOn(string $hex): string
    {
        $luminance = self::luminance($hex);

        return 1.05 / ($luminance + 0.05) >= ($luminance + 0.05) / 0.05 ? '#ffffff' : '#000000';
    }

    /**
     * The WCAG contrast ratio of two hex colors, from 1 to 21.
     */
    public static function contrast(string $first, string $second): float
    {
        $lighter = max(self::luminance($first), self::luminance($second));
        $darker = min(self::luminance($first), self::luminance($second));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $digits = ltrim($hex, '#');
        $digits = strlen($digits) < 6 ? implode('', array_map(fn (string $digit): string => $digit.$digit, str_split($digits))) : $digits;
        [$red, $green, $blue] = array_map(hexdec(...), str_split(substr($digits, 0, 6), 2));

        return 0.2126 * self::linear($red) + 0.7152 * self::linear($green) + 0.0722 * self::linear($blue);
    }

    private static function linear(int|float $channel): float
    {
        $value = $channel / 255;

        return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }
}
