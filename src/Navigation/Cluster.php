<?php

namespace Xelqun\Panels\Navigation;

use Illuminate\Support\Str;

/**
 * One sidebar link for several pages and resources, which then get tabs between
 * them. Set $cluster on each of them to this class. Their URLs start with its
 * slug. It has no page of its own, so its link goes to the first member the user
 * can reach.
 *
 * @api
 */
abstract class Cluster
{
    protected static ?string $slug = null;

    protected static ?string $navigationLabel = null;

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = null;

    protected static int $navigationSort = 0;

    /**
     * What every member's URL starts with, as in /admin/settings/billing.
     */
    public static function getSlug(): string
    {
        return static::$slug ?? Str::kebab(class_basename(static::class));
    }

    public static function getLabel(): string
    {
        return static::$navigationLabel ?? Str::headline(class_basename(static::class));
    }

    /**
     * @param  array<int, NavigationItem>  $members  What this user can reach, in order.
     */
    public static function getNavigationItem(array $members): NavigationItem
    {
        return NavigationItem::make(static::getLabel())
            ->icon(static::$navigationIcon)
            ->group(static::$navigationGroup)
            ->sort(static::$navigationSort)
            ->url(fn (): ?string => $members[0]->getUrl())
            ->active(array_filter($members, fn (NavigationItem $member): bool => $member->isActive()) !== []);
    }
}
