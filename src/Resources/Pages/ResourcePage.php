<?php

namespace Xelqun\Panels\Resources\Pages;

use Illuminate\Database\Eloquent\Model;
use Inertia\Response;
use Xelqun\Panels\Pages\Page;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Resources\RelationTable;
use Xelqun\Panels\Resources\Resource;

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

    public function getSubNavigation(): array
    {
        $cluster = $this->resource::getCluster();

        return $cluster === null ? [] : app(Panels::class)->current()->getClusterNavigation($cluster);
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
            ['label' => $this->resource::getNavigationLabel(), 'url' => $this->resource::getUrl()],
            ['label' => $this->getHeading()],
        ];
    }
}
