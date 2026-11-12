<?php

namespace Xelqun\Panels\Support;

/**
 * A panel's style, for Panel::preset(). Each changes shape only: corners,
 * shadows and borders. Colors come from Panel::colors(), except in Mono.
 *
 * @api
 */
enum Preset: string
{
    /** Rounded corners, a light edge on solid buttons and a dim gray dark theme. */
    case Default = 'default';

    /** Rounder, with pill buttons, softer shadows and quieter borders. */
    case Soft = 'soft';

    /** Square corners and a firmer border on every edge. */
    case Sharp = 'sharp';

    /** Black, white and neutral grays only: buttons, focus rings and links included. */
    case Mono = 'mono';
}
