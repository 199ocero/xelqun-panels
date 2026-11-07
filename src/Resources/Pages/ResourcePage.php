<?php

namespace Xelqun\Panels\Resources\Pages;

use Illuminate\Database\Eloquent\Model;
use Inertia\Response;
use Xelqun\Panels\Navigation\NavigationItem;
use Xelqun\Panels\Pages\Page;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Resources\RelationTable;
use Xelqun\Panels\Resources\Resource;
use Xelqun\Panels\Widgets\PanelWidget;
use Xelqun\Panels\Widgets\Widget;

/**
 * A page that works for any resource. The route says which, so a resource needs
 * no page classes of its own unless it wants to change one.
 *
 * Records come through the resource's query, never route model binding, and a
 * record is found before its policy is asked. So one out of reach is a 404,
 * which doesn't confirm that it exists.
 *
 * @api
 */
abstract class ResourcePage extends Page
{
    /** @var class-string<\Xelqun\Panels\Resources\Resource> */
    protected string $resource;

    protected ?Model $record = null;

    /**
     * @param  class-string<\Xelqun\Panels\Resources\Resource>  $resource
     */
    public function forResource(string $resource, ?Model $record = null): static
    {
        $this->resource = $resource;
        $this->record = $record;

        return $this;
    }

    /**
     * @return class-string<\Xelqun\Panels\Resources\Resource>
     */
    public function getResource(): string
    {
        return $this->resource;
    }

    public function getRecord(): ?Model
    {
        return $this->record;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function __invoke(): Response
    {
        $this->mountFromRoute();

        return parent::__invoke();
    }

    protected function mountFromRoute(): void
    {
        $route = request()->route();
        $this->resource = $route->parameter('resource');

        $key = $route->parameter('record');

        $this->record = $key === null ? null : $this->resource::findRecord($key);
    }

    protected function makeWidget(string $widget): PanelWidget
    {
        $made = parent::makeWidget($widget);

        return $made instanceof Widget ? $made->forRecord($this->record) : $made;
    }

    public function getSubNavigation(): array
    {
        if ($this->record?->exists && ($items = $this->resource::getRecordSubNavigation($this->record)) !== []) {
            return array_map(fn (NavigationItem $item): array => [...$item->toArray(), 'key' => $item->getLabel()], $items);
        }

        $cluster = $this->resource::getCluster();

        return $cluster === null ? [] : app(Panels::class)->current()->getClusterNavigation($cluster);
    }

    /**
     * @return array<int, array{label: string}>
     */
    protected function getClusterBreadcrumbs(): array
    {
        $cluster = $this->resource::getCluster();

        return $cluster === null ? [] : [['label' => $cluster::getLabel()]];
    }

    /**
     * The way back up through a nested resource's parent.
     *
     * @return array<int, array{label: string, url?: string}>
     */
    protected function getParentBreadcrumbs(): array
    {
        $parentResource = $this->resource::getParentResource();

        if ($parentResource === null) {
            return [];
        }

        $parent = $this->resource::getParentRecord();

        return [
            ['label' => $parentResource::getNavigationLabel(), 'url' => $parentResource::getUrl()],
            ['label' => $parentResource::getRecordTitle($parent), 'url' => $parentResource::getRecordUrl($parent) ?? $parentResource::getUrl()],
        ];
    }

    /**
     * @return array<int, RelationTable>
     */
    protected function getRelationTables(bool $isReadOnly): array
    {
        return array_map(
            fn (string $manager): RelationTable => RelationTable::for($this->resource, $manager, $this->record, $isReadOnly),
            $this->resource::getRelations(),
        );
    }

    protected function getAccessDeniedReason(): string
    {
        return config('app.debug') && ! $this->resource::hasPolicy()
            ? __('xelqun-panels::resources.needs_policy', [
                'model' => $this->resource::getModel(),
                'basename' => class_basename($this->resource::getModel()).'Policy',
            ])
            : '';
    }

    /**
     * @return array<int, array{label: string, url?: string}>
     */
    public function getBreadcrumbs(): array
    {
        return [
            ...$this->getClusterBreadcrumbs(),
            ...$this->getParentBreadcrumbs(),
            ['label' => $this->resource::getNavigationLabel(), 'url' => $this->resource::getUrl()],
            ['label' => $this->getHeading()],
        ];
    }
}
