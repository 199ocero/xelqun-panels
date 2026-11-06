<?php

namespace Xelqun\Panels\Resources;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;
use Xelqun\Tables\Table;

/**
 * Stands in for every relation manager's table, and its token says which. The
 * parent is found again through its resource on every request, so a parent out of
 * reach takes its related rows with it.
 *
 * @internal
 */
final class RelationTable extends Table
{
    /**
     * @param  class-string<\Xelqun\Panels\Resources\Resource>  $resource
     * @param  class-string<RelationManager>  $manager
     */
    public static function for(string $resource, string $manager, Model $owner, bool $isReadOnly): self
    {
        return self::make(['resource' => $resource, 'manager' => $manager, 'owner' => $owner->getKey(), 'readOnly' => $isReadOnly]);
    }

    protected function boot(): void
    {
        $manager = $this->getManager();

        $this->id($this->getResource()::getSlug().'-'.Str::kebab(class_basename($manager)).'-table');
        $this->emptyState(__('xelqun-panels::resources.empty', ['label' => mb_strtolower($manager::getTitle())]));

        $manager::configureTable($this);
    }

    public function getHeading(): string
    {
        return $this->getManager()::getTitle();
    }

    /**
     * Only the related table's columns, or a many-to-many's join would mix in the pivot's.
     */
    public function query(): Builder
    {
        $relation = $this->getManager()::getRelation($this->getOwner());

        return $relation->getQuery()->select($relation->getRelated()->qualifyColumn('*'));
    }

    public function authorize(?Authenticatable $user): bool
    {
        $owner = $this->getOwner();

        return $this->getResource()::can('view', $owner) && $this->getManager()::canViewFor($owner);
    }

    public function columns(): array
    {
        return $this->getManager()::columns();
    }

    public function filters(): array
    {
        return $this->getManager()::filters();
    }

    public function headerActions(): array
    {
        return $this->canChange() ? $this->getManager()::headerActions($this->getOwner()) : [];
    }

    public function recordActions(): array
    {
        return $this->canChange() ? $this->getManager()::recordActions($this->getOwner()) : [];
    }

    public function bulkActions(): array
    {
        return $this->canChange() ? $this->getManager()::bulkActions($this->getOwner()) : [];
    }

    private function canChange(): bool
    {
        return ! ($this->context['readOnly'] ?? true) && $this->getResource()::can('update', $this->getOwner());
    }

    /**
     * Kept on the request, because each row's actions build this table again.
     */
    private function getOwner(): Model
    {
        $resource = $this->getResource();
        $cacheKey = "xelqun-panels.owner.{$resource}.{$this->context['owner']}";
        $attributes = request()->attributes;

        if (! $attributes->has($cacheKey)) {
            $attributes->set($cacheKey, $resource::findRecord($this->context['owner']));
        }

        return $attributes->get($cacheKey);
    }

    /**
     * @return class-string<\Xelqun\Panels\Resources\Resource>
     */
    private function getResource(): string
    {
        $resource = $this->context['resource'] ?? null;

        if (! is_string($resource) || ! is_subclass_of($resource, Resource::class)) {
            throw new LogicException('A relation table needs its resource.');
        }

        return $resource;
    }

    /**
     * @return class-string<RelationManager>
     */
    private function getManager(): string
    {
        $manager = $this->context['manager'] ?? null;

        if (! is_string($manager) || ! is_subclass_of($manager, RelationManager::class)) {
            throw new LogicException('A relation table needs its manager.');
        }

        return $manager;
    }
}
