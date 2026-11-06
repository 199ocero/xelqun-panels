<?php

namespace Xelqun\Panels\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Xelqun\Actions\Action;

/**
 * @api
 */
class DeleteAction extends Action
{
    /**
     * @param  class-string<\Xelqun\Panels\Resources\Resource>  $resource
     */
    public static function for(string $resource): static
    {
        return static::make('delete')
            ->label(__('xelqun-panels::resources.delete'))
            ->icon('trash')
            ->iconButton()
            ->color('danger')
            ->authorize('delete')
            ->requiresConfirmation()
            ->visible(fn (?Model $record): bool => ! $resource::isTrashed($record))
            ->successNotificationTitle(__('xelqun-panels::resources.deleted', ['label' => Str::ucfirst($resource::getModelLabel())]))
            ->action(fn (Model $record) => $record->delete());
    }
}
