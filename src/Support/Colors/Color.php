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

    private const float AA_TEXT = 4.5;

    /** Below this it's a gray, whatever its hue says. */
    private const float MIN_CHROMA = 0.04;

    /** OKLCH hues that go brown when darkened: amber through lime. */
    private const array BRIGHT_HUES = [50.0, 135.0];

    /** About a Tailwind 400 shade. */
    private const float BRIGHT_FILL = 0.82;

    private const float DARK_TEXT = 0.28;

    private const float DARK_TEXT_CHROMA = 0.55;

    /**
     * The neutrals, Slate through Olive, for gray and secondary.
     */
    public function isGray(): bool
    {
        return in_array($this, [self::Slate, self::Gray, self::Zinc, self::Neutral, self::Stone, self::Taupe, self::Mauve, self::Mist, self::Olive], true);
    }

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
     * The fill and text for a solid surface in this color, such as a button.
     *
     * White on a color reads best, even where WCAG's formula scores black higher,
     * as it does on a mid-tone green. So the fill deepens until white passes AA,
     * keeping its hue. Yellows, ambers and limes turn brown that way, so they go
     * bright instead, with dark text in their own hue.
     *
     * @return array{fill: string, text: string}
     */
    public static function solidFor(string $hex): array
    {
        [$lightness, $a, $b] = self::toOklab($hex);
        $hue = rad2deg(atan2($b, $a));

        if (hypot($a, $b) > self::MIN_CHROMA && $hue >= self::BRIGHT_HUES[0] && $hue <= self::BRIGHT_HUES[1]) {
            return [
                'fill' => self::fromOklab(max($lightness, self::BRIGHT_FILL), $a, $b),
                'text' => self::fromOklab(self::DARK_TEXT, $a * self::DARK_TEXT_CHROMA, $b * self::DARK_TEXT_CHROMA),
            ];
        }

        $fill = substr($hex, 0, 7);

        for ($step = $lightness; self::contrast($fill, '#ffffff') < self::AA_TEXT; $step -= 0.01) {
            $fill = self::fromOklab($step, $a, $b);
        }

        return ['fill' => $fill, 'text' => '#ffffff'];
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
        [$red, $green, $blue] = self::toLinearRgb($hex);

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private static function toLinearRgb(string $hex): array
    {
        $digits = ltrim($hex, '#');
        $digits = strlen($digits) < 6 ? implode('', array_map(fn (string $digit): string => $digit.$digit, str_split($digits))) : $digits;
        [$red, $green, $blue] = array_map(hexdec(...), str_split(substr($digits, 0, 6), 2));

        return [self::linear($red), self::linear($green), self::linear($blue)];
    }

    /**
     * OKLab, where lightness can change without the hue drifting.
     *
     * @return array{0: float, 1: float, 2: float}
     */
    private static function toOklab(string $hex): array
    {
        [$red, $green, $blue] = self::toLinearRgb($hex);

        $l = (0.4122214708 * $red + 0.5363325363 * $green + 0.0514459929 * $blue) ** (1 / 3);
        $m = (0.2119034982 * $red + 0.6806995451 * $green + 0.1073969566 * $blue) ** (1 / 3);
        $s = (0.0883024619 * $red + 0.2817188376 * $green + 0.6299787005 * $blue) ** (1 / 3);

        return [
            0.2104542553 * $l + 0.793617785 * $m - 0.0040720468 * $s,
            1.9779984951 * $l - 2.428592205 * $m + 0.4505937099 * $s,
            0.0259040371 * $l + 0.7827717662 * $m - 0.808675766 * $s,
        ];
    }

    private static function fromOklab(float $lightness, float $a, float $b): string
    {
        $l = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s = ($lightness - 0.0894841775 * $a - 1.291485548 * $b) ** 3;

        return sprintf(
            '#%02x%02x%02x',
            self::toChannel(4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s),
            self::toChannel(-1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s),
            self::toChannel(-0.0041960863 * $l - 0.7034186147 * $m + 1.707614701 * $s),
        );
    }

    private static function toChannel(float $linear): int
    {
        $value = max(0.0, min(1.0, $linear));
        $encoded = $value <= 0.0031308 ? 12.92 * $value : 1.055 * $value ** (1 / 2.4) - 0.055;

        return (int) round($encoded * 255);
    }

    private static function linear(int|float $channel): float
    {
        $value = $channel / 255;

        return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }
}
