<?php

namespace Xelqun\Panels\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Xelqun\Panels\Contracts\HasPanelAppearance;
use Xelqun\Panels\Support\Colors\Color;
use Xelqun\Panels\Support\Preset;

/**
 * Saves what someone picks from the theme switcher on their account. Only the
 * switcher's own choices are accepted: a style, a color for primary and a gray
 * for secondary.
 */
final class AppearanceController
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof HasPanelAppearance, 404);

        $grays = array_column(array_filter(Color::cases(), fn (Color $color): bool => $color->isGray()), 'name');
        $colors = array_values(array_diff(array_column(Color::cases(), 'name'), $grays));

        $appearance = $request->validate([
            'preset' => ['nullable', Rule::enum(Preset::class)],
            'primary' => ['nullable', Rule::in($colors)],
            'secondary' => ['nullable', Rule::in($grays)],
        ]);

        $user->savePanelAppearance(array_filter($appearance, fn (mixed $value): bool => $value !== null));

        return response()->noContent();
    }
}
