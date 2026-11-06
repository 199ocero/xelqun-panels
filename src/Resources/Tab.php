<?php

namespace Xelqun\Panels\Resources;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A slice of a resource's list. Return some from Resource::getTabs().
 *
 * @api
 */
final class Tab
{
    private ?string $icon = null;

    private string|int|Closure|null $badge = null;

    private ?Closure $modifyQuery = null;

    private function __construct(private readonly ?string $label) {}

    /**
     * With no label, the tab's key is used.
     */
    public static function make(?string $label = null): self
    {
        return new self($label);
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Use a closure so a count only runs for the page that shows it.
     */
    public function badge(string|int|Closure|null $badge): self
    {
        $this->badge = $badge;

        return $this;
    }

    /**
     * @param  Closure(Builder<Model>): mixed  $using
     */
    public function modifyQueryUsing(Closure $using): self
    {
        $this->modifyQuery = $using;

        return $this;
    }

    /**
     * Grouped, so an orWhere in the tab can't step outside the resource's scope.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyTo(Builder $query): Builder
    {
        return $this->modifyQuery === null ? $query : $query->where(fn (Builder $tabQuery) => ($this->modifyQuery)($tabQuery));
    }

    /**
     * @return array{key: string, label: string, icon: string|null, badge: string|null}
     */
    public function toArray(string $key): array
    {
        $badge = $this->badge instanceof Closure ? ($this->badge)() : $this->badge;

        return [
            'key' => $key,
            'label' => $this->label ?? Str::headline($key),
            'icon' => $this->icon,
            'badge' => $badge === null ? null : (string) $badge,
        ];
    }
}
