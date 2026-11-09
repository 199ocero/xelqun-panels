<?php

namespace Xelqun\Panels\Navigation;

/**
 * A group in the sidebar, with an icon and, if you like, a way to fold it.
 * Pass it to Panel::navigationGroups(), where its place sets the order.
 *
 * @api
 */
final class NavigationGroup
{
    private ?string $icon = null;

    private bool $isCollapsible = false;

    private bool $isCollapsed = false;

    private function __construct(private readonly string $label) {}

    public static function make(string $label): self
    {
        return new self($label);
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Let the viewer fold it away. The browser remembers the choice.
     */
    public function collapsible(bool $condition = true): self
    {
        $this->isCollapsible = $condition;

        return $this;
    }

    /**
     * Start folded, until the viewer opens it. This makes it collapsible.
     */
    public function collapsed(bool $condition = true): self
    {
        $this->isCollapsed = $condition;
        $this->isCollapsible = $this->isCollapsible || $condition;

        return $this;
    }

    /**
     * @return array{icon?: string, collapsible?: true, collapsed?: true}
     */
    public function toArray(): array
    {
        return array_filter([
            'icon' => $this->icon,
            'collapsible' => $this->isCollapsible ?: null,
            'collapsed' => $this->isCollapsed ?: null,
        ]);
    }
}
