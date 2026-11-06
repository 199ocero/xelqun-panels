<?php

namespace Xelqun\Panels\Resources\Pages;

use Illuminate\Support\Str;
use Xelqun\Actions\Action;
use Xelqun\Panels\Resources\ResourceTable;

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
        return [['label' => $this->getHeading()]];
    }

    public function isAccessible(): bool
    {
        return $this->resource::can('viewAny');
    }

    public function headerActions(): array
    {
        return [
            Action::make('create')
                ->label(__('xelqun-panels::resources.new', ['label' => $this->resource::getModelLabel()]))
                ->icon('plus')
                ->authorize('create')
                ->url($this->resource::getUrl('create')),
        ];
    }

    public function content(): array
    {
        return [ResourceTable::for($this->resource)];
    }
}
