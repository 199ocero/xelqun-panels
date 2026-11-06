<?php

namespace Xelqun\Panels\Resources\Pages;

use Xelqun\Actions\Action;
use Xelqun\Panels\Pages\FormContent;
use Xelqun\Panels\Resources\ResourceForm;

/**
 * The record's form with nothing to change.
 *
 * @api
 */
class ViewRecord extends ResourcePage
{
    public function getHeading(): string
    {
        return __('xelqun-panels::resources.view', ['label' => $this->resource::getRecordTitle($this->record)]);
    }

    public function isAccessible(): bool
    {
        return $this->resource::can('view', $this->record);
    }

    public function headerActions(): array
    {
        return ! $this->resource::hasPage('edit') ? [] : [
            Action::make('edit')
                ->label(__('xelqun-panels::resources.edit_action'))
                ->icon('pencil')
                ->authorize('update')
                ->url($this->resource::getUrl('edit', ['record' => $this->record])),
        ];
    }

    public function content(): array
    {
        return [
            new FormContent(
                ResourceForm::for($this->resource, ResourceForm::VIEW, $this->record),
                $this->resource::getUrl('view', ['record' => $this->record]),
            ),
            ...$this->getRelationTables(isReadOnly: true),
        ];
    }
}
