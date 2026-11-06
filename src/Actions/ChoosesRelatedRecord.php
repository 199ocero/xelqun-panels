<?php

namespace Xelqun\Panels\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Components\Select;
use Xelqun\Panels\Resources\RelationManager;

/**
 * A modal with one Select of records that could be linked.
 */
trait ChoosesRelatedRecord
{
    /** Enough to choose from without sending a whole table to the browser. */
    private const int MAX_OPTIONS = 200;

    /**
     * @param  class-string<RelationManager>  $manager
     * @param  Builder<Model>  $available
     */
    protected static function chooser(string $name, string $manager, Builder $available): static
    {
        return static::make($name)
            ->authorize(true)
            ->schema(fn (): array => [
                Select::make('record')
                    ->label(__('xelqun-panels::resources.record'))
                    ->required()
                    ->searchable()
                    // shortcut: a table with more rows than this can't reach the rest. Search on the server if one needs to.
                    ->options(fn (): array => (clone $available)->limit(self::MAX_OPTIONS)->pluck($manager::getRecordTitleAttribute(), $available->getModel()->getKeyName())->all()),
            ]);
    }
}
