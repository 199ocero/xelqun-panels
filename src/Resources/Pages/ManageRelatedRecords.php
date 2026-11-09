<?php

namespace Xelqun\Panels\Resources\Pages;

use Xelqun\Panels\Resources\RelationManager;

/**
 * One relation manager on a page of its own, under the record, instead of
 * under its form. Name the manager, and add the page to getPages():
 *
 *     'comments' => ManagePostComments::class,
 *
 * @api
 */
abstract class ManageRelatedRecords extends ResourcePage
{
    /** @var class-string<RelationManager> */
    protected static string $relationManager;

    public static function getRoutePath(string $key): string
    {
        return trim(static::$routePath ?? '{record}/'.$key, '/');
    }

    public function getHeading(): string
    {
        return static::$relationManager::getTitle();
    }

    public function isAccessible(): bool
    {
        return $this->resource::can('view', $this->record);
    }

    public function content(): array
    {
        return $this->getRelationTables(isReadOnly: false, managers: [static::$relationManager]);
    }
}
