<?php

namespace Xelqun\Panels\Resources;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Xelqun\Tables\Table;

/**
 * Stands in for every resource's table, and its token says which. The resource's
 * query is what every row and action goes through.
 *
 * @internal
 */
final class ResourceTable extends Table
{
    /**
     * @param  class-string<\Xelqun\Panels\Resources\Resource>  $resource
     */
    public static function for(string $resource, ?string $tab = null): self
    {
        return self::make(['resource' => $resource, 'tab' => $tab]);
    }

    protected function boot(): void
    {
        $resource = $this->getResource();

        // Each tab has its own id, so changing tab starts a fresh table and not one that thinks it hasn't moved.
        $this->id(implode('-', array_filter([$resource::getSlug(), $this->context['tab'] ?? null, 'table'])));
        $this->emptyState(__('xelqun-panels::resources.empty', ['label' => $resource::getPluralModelLabel()]));

        $resource::configureTable($this);
    }

    public function query(): Builder
    {
        $resource = $this->getResource();
        $tab = $resource::getTabs()[$this->context['tab'] ?? ''] ?? null;

        return $tab === null ? $resource::getEloquentQuery() : $tab->applyTo($resource::getEloquentQuery());
    }

    public function authorize(?Authenticatable $user): bool
    {
        return $this->getResource()::can('viewAny');
    }

    public function columns(): array
    {
        return $this->getResource()::columns();
    }

    public function filters(): array
    {
        return $this->getResource()::filters();
    }

    public function recordActions(): array
    {
        return $this->getResource()::recordActions();
    }

    public function bulkActions(): array
    {
        return $this->getResource()::bulkActions();
    }

    /**
     * Edit if the viewer can, or else view.
     */
    public function recordUrl(Model $record): ?string
    {
        return $this->getResource()::getRecordUrl($record);
    }

    /**
     * @return class-string<\Xelqun\Panels\Resources\Resource>
     */
    private function getResource(): string
    {
        $resource = $this->context['resource'] ?? null;

        if (! is_string($resource) || ! is_subclass_of($resource, Resource::class)) {
            throw new LogicException('A resource table needs its resource.');
        }

        return $resource;
    }
}
