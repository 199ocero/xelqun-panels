<?php

namespace Xelqun\Panels\Widgets;

use Xelqun\Forms\Support\SafeUrl;

/**
 * One card on a TileWidget.
 *
 * @api
 */
final class Tile
{
    private ?string $icon = null;

    private ?string $status = null;

    private string $color = 'gray';

    private ?string $url = null;

    private function __construct(private readonly string $label) {}

    public static function make(string $label): self
    {
        return new self($label);
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @param  'primary'|'success'|'warning'|'danger'|'gray'  $color
     */
    public function status(?string $status, string $color = 'gray'): self
    {
        $this->status = $status;
        $this->color = $color;

        return $this;
    }

    public function url(?string $url): self
    {
        $this->url = SafeUrl::filter($url);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'icon' => $this->icon,
            'status' => $this->status,
            'color' => $this->color,
            'url' => $this->url,
        ];
    }
}
