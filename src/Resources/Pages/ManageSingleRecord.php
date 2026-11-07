<?php

namespace Xelqun\Panels\Resources\Pages;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Xelqun\Panels\Pages\FormContent;
use Xelqun\Panels\Resources\ResourceForm;

/**
 * One record and no list, as for site settings. Make it the resource's only page.
 * The record comes from Resource::getSingularRecord().
 *
 * @api
 */
class ManageSingleRecord extends EditRecord
{
    protected function mountFromRoute(): void
    {
        $this->resource = request()->route()->parameter('resource');
        $this->record = $this->resource::getSingularRecord();
    }

    public function getHeading(): string
    {
        return Str::headline($this->resource::getModelLabel());
    }

    public function getBreadcrumbs(): array
    {
        return [['label' => $this->getHeading()]];
    }

    public function headerActions(): array
    {
        return [];
    }

    public function content(): array
    {
        return [new FormContent(
            ResourceForm::for($this->resource, ResourceForm::EDIT, $this->record),
            $this->resource::getUrl('update'),
            'put',
            __('xelqun-panels::resources.save'),
        )];
    }

    public function update(Request $request): RedirectResponse
    {
        parent::update($request);

        return redirect()->to($this->resource::getUrl());
    }
}
