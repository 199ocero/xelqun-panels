<?php

namespace Xelqun\Panels\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Xelqun\Actions\Action;

/**
 * Removes a soft-deleted record for good. Needs `forceDelete` in the policy.
 *
 * @api
 */
class ForceDeleteAction extends Action
{
    /**
     * @param  class-string<\Xelqun\Panels\Resources\Resource>  $resource
     */
    public static function for(string $resource): static
    {
        return static::make('forceDelete')
            ->label(__('xelqun-panels::resources.force_delete'))
            ->icon('trash')
            ->iconButton()
            ->color('danger')
            ->authorize('forceDelete')
            ->requiresConfirmation()
            ->visible(fn (?Model $record): bool => $resource::isTrashed($record))
            ->successNotificationTitle(__('xelqun-panels::resources.deleted', ['label' => Str::ucfirst($resource::getModelLabel())]))
            ->action(fn (Model $record) => $record->forceDelete());
    }
}
