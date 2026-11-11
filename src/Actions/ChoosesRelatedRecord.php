<?php

namespace Xelqun\Panels\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Xelqun\Forms\Components\Select;
use Xelqun\Panels\Resources\RelationManager;

/**
 * A modal with one Select of records that could be linked. It asks the related
 * model's policy for the action's name, such as attach, and offers only records
 * the user may view.
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
            ->authorize($name)
            ->schema(fn (): array => [
                Select::make('record')
                    ->label(__('xelqun-panels::resources.record'))
                    ->required()
                    ->searchable()
                    // shortcut: a table with more rows than this can't reach the rest. Search on the server if one needs to.
                    ->options(fn (): array => (clone $available)->limit(self::MAX_OPTIONS)->get()
                        ->filter(fn (Model $record): bool => Gate::allows('view', $record))
                        ->pluck($manager::getRecordTitleAttribute(), $available->getModel()->getKeyName())
                        ->all()),
            ]);
    }

    /**
     * The chosen record, looked up again, so a posted key can only name one the list offered.
     *
     * @param  Builder<Model>  $available
     */
    protected static function chosen(Builder $available, mixed $key): Model
    {
        $record = $available->whereKey($key)->firstOrFail();

        abort_unless(Gate::allows('view', $record), 404);

        return $record;
    }
}
