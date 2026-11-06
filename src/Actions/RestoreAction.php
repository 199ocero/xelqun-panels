<?php

namespace Xelqun\Panels\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Xelqun\Actions\Action;

/**
 * Brings back a soft-deleted record. Needs `restore` in the policy.
 *
 * @api
 */
class RestoreAction extends Action
{
    /**
     * @param  class-string<\Xelqun\Panels\Resources\Resource>  $resource
     */
    public static function for(string $resource): static
    {
        return static::make('restore')
            ->label(__('xelqun-panels::resources.restore'))
            ->icon('rotate-ccw')
            ->iconButton()
            ->authorize('restore')
            ->requiresConfirmation()
            ->visible(fn (?Model $record): bool => $resource::isTrashed($record))
            ->successNotificationTitle(__('xelqun-panels::resources.restored', ['label' => Str::ucfirst($resource::getModelLabel())]))
            ->action(fn (Model $record) => $resource::restoreRecord($record));
    }
}
