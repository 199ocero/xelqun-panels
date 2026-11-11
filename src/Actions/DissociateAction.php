<?php

namespace Xelqun\Panels\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Xelqun\Actions\Action;
use Xelqun\Panels\Resources\RelationManager;

/**
 * Takes a record away from its parent, leaving the record itself. The foreign key
 * must be nullable.
 *
 * @api
 */
class DissociateAction extends Action
{
    /**
     * @param  class-string<RelationManager>  $manager
     */
    public static function for(string $manager, Model $owner): static
    {
        return static::make('dissociate')
            ->label(__('xelqun-panels::resources.dissociate'))
            ->icon('unlink')
            ->iconButton()
            ->color('danger')
            ->authorize('dissociate')
            ->requiresConfirmation()
            ->successNotificationTitle(__('xelqun-panels::resources.dissociated'))
            ->action(function (Model $record) use ($manager, $owner): void {
                $relation = $manager::getRelation($owner);

                if ($relation instanceof HasOneOrMany) {
                    $record->setAttribute($relation->getForeignKeyName(), null)->save();
                }
            });
    }
}
