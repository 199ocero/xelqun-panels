<?php

namespace Xelqun\Panels\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use LogicException;
use Xelqun\Actions\Action;
use Xelqun\Panels\Resources\RelationManager;

/**
 * Gives a parent to an existing record of a has-many. Only records with no parent
 * are offered, so this can't take one away from another parent.
 *
 * @api
 */
class AssociateAction extends Action
{
    use ChoosesRelatedRecord;

    /**
     * @param  class-string<RelationManager>  $manager
     */
    public static function for(string $manager, Model $owner): static
    {
        return static::chooser('associate', $manager, static::available($manager, $owner))
            ->label(__('xelqun-panels::resources.associate'))
            ->icon('link')
            ->successNotificationTitle(__('xelqun-panels::resources.associated'))
            ->action(fn (array $data) => static::relation($manager, $owner)->save(static::available($manager, $owner)->whereKey($data['record'])->firstOrFail()));
    }

    /**
     * @param  class-string<RelationManager>  $manager
     * @return Builder<Model>
     */
    protected static function available(string $manager, Model $owner): Builder
    {
        return $manager::getLinkableQuery($owner)->whereNull(static::relation($manager, $owner)->getForeignKeyName());
    }

    /**
     * @param  class-string<RelationManager>  $manager
     * @return HasOneOrMany<Model, Model, mixed>
     */
    protected static function relation(string $manager, Model $owner): HasOneOrMany
    {
        $relation = $manager::getRelation($owner);

        return $relation instanceof HasOneOrMany ? $relation : throw new LogicException('AssociateAction needs a has-many relationship.');
    }
}
