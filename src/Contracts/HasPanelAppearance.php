<?php

namespace Xelqun\Panels\Contracts;

/**
 * Lets a user keep the style and colors they pick from the theme switcher on
 * their account, so the choice follows them to every browser. The
 * InteractsWithPanelAppearance trait is all of this.
 *
 * @api
 */
interface HasPanelAppearance
{
    /**
     * @return array{preset?: string, primary?: string, secondary?: string}
     */
    public function getPanelAppearance(): array;

    /**
     * @param  array{preset?: string, primary?: string, secondary?: string}  $appearance  Checked against the switcher's choices first. Empty resets it.
     */
    public function savePanelAppearance(array $appearance): void;
}
