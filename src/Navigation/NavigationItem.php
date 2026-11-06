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

    public function active(bool $isActive = true): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return array{label: string, icon: string|null, url: string|null, active: bool, badge: string|null}
     */
    public function toArray(): array
    {
        $badge = $this->badge instanceof Closure ? ($this->badge)() : $this->badge;
        $url = $this->url instanceof Closure ? ($this->url)() : $this->url;

        return [
            'label' => $this->label,
            'icon' => $this->icon,
            'url' => SafeUrl::filter($url),
            'active' => $this->isActive,
            'badge' => $badge === null ? null : (string) $badge,
        ];
    }
}
