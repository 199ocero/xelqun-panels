<?php

namespace Xelqun\Panels\Resources;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use LogicException;
use Xelqun\Panels\Tables\TrashedFilter;
use Xelqun\Tables\Filters\BaseFilter;
use Xelqun\Tables\State\TableState;
use Xelqun\Tables\Table;

/**
 * Stands in for every resource's table, and its token says which. The resource's
 * query is what every row and action goes through.
 *
 * @internal
 */
final class ResourceTable extends Table
{
    private bool $hidesTrashed = false;

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
        $query = $tab === null ? $resource::getEloquentQuery() : $tab->applyTo($resource::getEloquentQuery());

        if (! $this->hasTrashedFilter()) {
            return $query;
        }

        // Restore has to be able to find a trashed row, so only what's listed hides them.
        return $query->withoutGlobalScope(SoftDeletingScope::class)->when(
            $this->hidesTrashed,
            fn (Builder $visible): Builder => $visible->whereNull($this->getResource()::getDeletedAtColumn()),
        );
    }

    public function toResult(TableState $state): array
    {
        $this->hidesTrashed = $this->hasTrashedFilter() && ! $this->wantsTrashed($state);

        return parent::toResult($state);
    }

    public function getSelectableKeys(TableState $state): ?array
    {
        $this->hidesTrashed = $this->hasTrashedFilter() && ! $this->wantsTrashed($state);

        return parent::getSelectableKeys($state);
    }

    private function hasTrashedFilter(): bool
    {
        return collect($this->getFilters())->contains(fn (BaseFilter $filter): bool => $filter instanceof TrashedFilter);
    }

    private function wantsTrashed(TableState $state): bool
    {
        return collect($this->getFilters())->contains(fn (BaseFilter $filter): bool => $filter instanceof TrashedFilter && $filter->isActive($state->filters));
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
