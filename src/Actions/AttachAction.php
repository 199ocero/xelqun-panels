<?php

namespace Xelqun\Panels\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;
use Xelqun\Actions\Action;
use Xelqun\Panels\Resources\RelationManager;

/**
 * Links an existing record through a many-to-many. Put it in a relation manager's
 * headerActions(). The record is looked up again on submit, so a posted id can
 * only name something the list offered.
 *
 * @api
 */
class AttachAction extends Action
{
    use ChoosesRelatedRecord;

    /**
     * @param  class-string<RelationManager>  $manager
     */
    public static function for(string $manager, Model $owner): static
    {
        return static::chooser('attach', $manager, static::available($manager, $owner))
            ->label(__('xelqun-panels::resources.attach'))
            ->icon('link')
            ->successNotificationTitle(__('xelqun-panels::resources.attached'))
            ->action(fn (array $data) => static::relation($manager, $owner)->attach(static::chosen(static::available($manager, $owner), $data['record'])));
    }

    /**
     * @param  class-string<RelationManager>  $manager
     * @return Builder<Model>
     */
    protected static function available(string $manager, Model $owner): Builder
    {
        return $manager::getLinkableQuery($owner)->whereKeyNot(static::relation($manager, $owner)->allRelatedIds());
    }

    /**
     * @param  class-string<RelationManager>  $manager
     * @return BelongsToMany<Model, Model>
     */
    protected static function relation(string $manager, Model $owner): BelongsToMany
    {
        $relation = $manager::getRelation($owner);

        return $relation instanceof BelongsToMany ? $relation : throw new LogicException('AttachAction needs a many-to-many relationship.');
    }
}
