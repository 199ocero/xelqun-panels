<?php

namespace Xelqun\Panels\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Xelqun\Actions\Action;
use Xelqun\Panels\Resources\Resource;
use Xelqun\Panels\Resources\ResourceForm;

/**
 * Goes to the edit page, or opens a modal for a one-page resource.
 *
 * @api
 */
class EditAction extends Action
{
    /**
     * @param  class-string<\Xelqun\Panels\Resources\Resource>  $resource
     */
    public static function for(string $resource): static
    {
        $action = static::make('edit')
            ->label(__('xelqun-panels::resources.edit_action'))
            ->icon('pencil')
            ->iconButton()
            ->authorize('update')
            ->visible(fn (?Model $record): bool => ! $resource::isTrashed($record));

        return $resource::hasPage('edit')
            ? $action->url(fn (Model $record): string => $resource::getUrl('edit', ['record' => $record]))
            : $action
                ->schema(fn (): array => $resource::form())
                ->modalSubmitActionLabel(__('xelqun-panels::resources.save'))
                ->successNotificationTitle(__('xelqun-panels::resources.saved', ['label' => Str::ucfirst($resource::getModelLabel())]))
                ->action(fn (array $data, Model $record): Model => ResourceForm::for($resource, ResourceForm::EDIT, $record)->persist($data));
    }
}
