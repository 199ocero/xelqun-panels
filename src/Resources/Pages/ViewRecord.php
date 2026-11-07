<?php

namespace Xelqun\Panels\Resources\Pages;

use Xelqun\Panels\Actions\EditAction;
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
        return ! $this->resource::hasPage('edit') ? [] : [EditAction::for($this->resource)->button()];
    }

    protected function getWidgets(): array
    {
        return $this->resource::getRecordWidgets();
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
