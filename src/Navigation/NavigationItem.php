<?php

namespace Xelqun\Panels\Navigation;

use Closure;
use Xelqun\Forms\Support\SafeUrl;

/**
 * One link in the sidebar. Pages make their own, so this is for links to
 * anything else. Add them with Panel::navigationItems().
 *
 * @api
 */
final class NavigationItem
{
    private ?string $icon = null;

    private string|Closure|null $url = null;

    private ?string $group = null;

    private int $sort = 0;

    private string|int|Closure|null $badge = null;

    private bool $isActive = false;

    private ?string $badgeColor = null;

    private ?string $badgeTooltip = null;

    private ?string $parentItem = null;

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
     * Use a closure for route(): a panel is declared before routes exist.
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    public function getUrl(): ?string
    {
        return SafeUrl::filter($this->url instanceof Closure ? ($this->url)() : $this->url);
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function url(string|Closure $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function group(?string $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function sort(int $sort): self
    {
        $this->sort = $sort;

        return $this;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    public function badge(string|int|Closure|null $badge): self
    {
        $this->badge = $badge;

        return $this;
    }

    /**
     * primary, danger, info, success, warning or gray. Plain text unless you choose.
     */
    public function badgeColor(?string $color): self
    {
        $this->badgeColor = $color;

        return $this;
    }

    public function badgeTooltip(?string $tooltip): self
    {
        $this->badgeTooltip = $tooltip;

        return $this;
    }

    /**
     * Sit under another item of the same group, by its label.
     */
    public function parentItem(?string $label): self
    {
        $this->parentItem = $label;

        return $this;
    }

    public function getParentItem(): ?string
    {
        return $this->parentItem;
    }

    public function active(bool $isActive = true): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $badge = $this->badge instanceof Closure ? ($this->badge)() : $this->badge;

        return [
            'label' => $this->label,
            'icon' => $this->icon,
            'url' => $this->getUrl(),
            'active' => $this->isActive,
            'badge' => $badge === null ? null : (string) $badge,
            ...array_filter(['badgeColor' => $this->badgeColor, 'badgeTooltip' => $this->badgeTooltip]),
        ];
    }
}
