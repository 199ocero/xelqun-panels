<?php

namespace Xelqun\Panels\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Xelqun\Actions\Action;
use Xelqun\Panels\Resources\RelationManager;

/**
 * Unlinks a record from a many-to-many, leaving the record itself.
 *
 * @api
 */
class DetachAction extends Action
{
    /**
     * @param  class-string<RelationManager>  $manager
     */
    public static function for(string $manager, Model $owner): static
    {
        return static::make('detach')
            ->label(__('xelqun-panels::resources.detach'))
            ->icon('unlink')
            ->iconButton()
            ->color('danger')
            ->authorize('detach')
            ->requiresConfirmation()
            ->successNotificationTitle(__('xelqun-panels::resources.detached'))
            ->action(function (Model $record) use ($manager, $owner): void {
                $relation = $manager::getRelation($owner);

                if ($relation instanceof BelongsToMany) {
                    $relation->detach($record);
                }
            });
    }
}
