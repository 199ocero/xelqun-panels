<?php

namespace Xelqun\Panels\Resources\Pages;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Xelqun\Notifications\Notification;
use Xelqun\Panels\Pages\FormContent;
use Xelqun\Panels\Resources\ResourceForm;

/**
 * @api
 */
class CreateRecord extends ResourcePage
{
    /** Offer a button that saves and opens a new, empty form. */
    protected static bool $canCreateAnother = true;

    public function getHeading(): string
    {
        return __('xelqun-panels::resources.create', ['label' => $this->resource::getModelLabel()]);
    }

    public function isAccessible(): bool
    {
        return $this->resource::can('create');
    }

    public function content(): array
    {
        return [new FormContent(
            ResourceForm::for($this->resource, ResourceForm::CREATE),
            $this->resource::getUrl('store'),
            'post',
            $this->getHeading(),
            static::$canCreateAnother ? __('xelqun-panels::resources.create_another') : null,
        )];
    }

    public function store(Request $request): RedirectResponse
    {
        $this->mountFromRoute();

        abort_unless($this->isAccessible(), 403, $this->getAccessDeniedReason());

        $record = ResourceForm::for($this->resource, ResourceForm::CREATE)->save($request);

        Notification::make()
            ->title(__('xelqun-panels::resources.created', ['label' => Str::ucfirst($this->resource::getModelLabel())]))
            ->success()
            ->send();

        return redirect()->to(static::$canCreateAnother && $request->boolean('another') ? $this->resource::getUrl('create') : $this->getRedirectUrl($record));
    }

    /**
     * Where to go next: somewhere this user can still see the new record.
     */
    protected function getRedirectUrl(Model $record): string
    {
        return match (true) {
            $this->resource::can('update', $record) => $this->resource::getUrl('edit', ['record' => $record]),
            $this->resource::can('view', $record) => $this->resource::getUrl('view', ['record' => $record]),
            default => $this->resource::getUrl(),
        };
    }
}
