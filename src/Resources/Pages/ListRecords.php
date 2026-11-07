<?php

namespace Xelqun\Panels\Resources\Pages;

use Illuminate\Support\Str;
use Xelqun\Actions\Action;
use Xelqun\Panels\Resources\ResourceTable;
use Xelqun\Panels\Resources\Tab;

/**
 * @api
 */
class ListRecords extends ResourcePage
{
    public function getHeading(): string
    {
        return Str::headline($this->resource::getPluralModelLabel());
    }

    public function getBreadcrumbs(): array
    {
        return [...$this->getParentBreadcrumbs(), ['label' => $this->getHeading()]];
    }

    public function isAccessible(): bool
    {
        return $this->resource::can('viewAny');
    }

    public function headerActions(): array
    {
        return [
            ...$this->resource::getHeaderActions(),
            ...(! $this->resource::hasPage('create') ? [] : [
                Action::make('create')
                    ->label(__('xelqun-panels::resources.new', ['label' => $this->resource::getModelLabel()]))
                    ->icon('plus')
                    ->authorize('create')
                    ->url($this->resource::getUrl('create')),
            ]),
        ];
    }

    public function content(): array
    {
        return [ResourceTable::for($this->resource, $this->getActiveTab())];
    }

    protected function getWidgets(): array
    {
        return $this->resource::getHeaderWidgets();
    }

    public function getTabs(): array
    {
        $activeTab = $this->getActiveTab();

        return array_map(
            fn (string $key, Tab $tab): array => [
                ...$tab->toArray($key),
                'url' => $this->resource::getUrl('index', ['tab' => $key]),
                'active' => $key === $activeTab,
            ],
            array_keys($this->resource::getTabs()),
            $this->resource::getTabs(),
        );
    }

    /**
     * From the URL if that's a tab, because the URL can say anything.
     */
    private function getActiveTab(): ?string
    {
        $tabs = $this->resource::getTabs();
        $key = request()->query('tab');

        return is_string($key) && isset($tabs[$key]) ? $key : array_key_first($tabs);
    }
}
