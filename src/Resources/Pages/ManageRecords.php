<?php

namespace Xelqun\Panels\Resources\Pages;

use Illuminate\Support\Str;
use Xelqun\Actions\Action;
use Xelqun\Panels\Resources\ResourceForm;

/**
 * Everything on one page, with create and edit in modals. Make it the resource's
 * only page, for a model with a few fields.
 *
 * @api
 */
class ManageRecords extends ListRecords
{
    public function headerActions(): array
    {
        $resource = $this->resource;

        return [
            Action::make('create')
                ->label(__('xelqun-panels::resources.new', ['label' => $resource::getModelLabel()]))
                ->icon('plus')
                ->authorize('create')
                ->schema(fn (): array => $resource::form())
                ->modalSubmitActionLabel(__('xelqun-panels::resources.create', ['label' => $resource::getModelLabel()]))
                ->successNotificationTitle(__('xelqun-panels::resources.created', ['label' => Str::ucfirst($resource::getModelLabel())]))
                ->action(fn (array $data) => ResourceForm::for($resource, ResourceForm::CREATE)->persist($data)),
        ];
    }
}
