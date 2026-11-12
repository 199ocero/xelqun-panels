<?php

namespace Xelqun\Panels\Auth\Concerns;

/**
 * Keeps a user's theme switcher choice in a JSON column. Publish it with
 * `php artisan vendor:publish --tag=xelqun-panels-appearance`.
 *
 * @api
 */
trait InteractsWithPanelAppearance
{
    public function initializeInteractsWithPanelAppearance(): void
    {
        $this->mergeCasts(['panel_appearance' => 'array']);
    }

    /**
     * @return array{preset?: string, primary?: string, secondary?: string}
     */
    public function getPanelAppearance(): array
    {
        $appearance = $this->getAttribute('panel_appearance');

        return is_array($appearance) ? $appearance : [];
    }

    /**
     * @param  array{preset?: string, primary?: string, secondary?: string}  $appearance
     */
    public function savePanelAppearance(array $appearance): void
    {
        $this->forceFill(['panel_appearance' => $appearance === [] ? null : $appearance])->save();
    }
}
