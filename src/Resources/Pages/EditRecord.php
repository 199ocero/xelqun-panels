<?php

namespace Xelqun\Panels\Resources\Pages;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Xelqun\Notifications\Notification;
use Xelqun\Panels\Actions\DeleteAction;
use Xelqun\Panels\Pages\FormContent;
use Xelqun\Panels\Resources\ResourceForm;

/**
 * @api
 */
class EditRecord extends ResourcePage
{
    public function getHeading(): string
    {
        return __('xelqun-panels::resources.edit', ['label' => $this->resource::getRecordTitle($this->record)]);
    }

    public function isAccessible(): bool
    {
        return $this->resource::can('update', $this->record);
    }

    public function headerActions(): array
    {
        return [
            DeleteAction::for($this->resource)
                ->button()
                ->successRedirectUrl($this->resource::getUrl()),
        ];
    }

    public function content(): array
    {
        return [
            new FormContent(
                ResourceForm::for($this->resource, ResourceForm::EDIT, $this->record),
                $this->resource::getUrl('update', ['record' => $this->record]),
                'put',
                __('xelqun-panels::resources.save'),
            ),
            ...$this->getRelationTables(isReadOnly: false),
        ];
    }

    public function update(Request $request): RedirectResponse
    {
        $this->mountFromRoute();

        abort_unless($this->isAccessible(), 403, $this->getAccessDeniedReason());

        ResourceForm::for($this->resource, ResourceForm::EDIT, $this->record)->save($request);

        Notification::make()
            ->title(__('xelqun-panels::resources.saved', ['label' => Str::ucfirst($this->resource::getModelLabel())]))
            ->success()
            ->send();

        return redirect()->to($this->resource::getRecordUrl($this->record) ?? $this->resource::getUrl());
    }
}
