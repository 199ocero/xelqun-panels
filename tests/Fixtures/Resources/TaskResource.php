<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Xelqun\Actions\ActionGroup;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Panels\Actions\ExportAction;
use Xelqun\Panels\Actions\ReplicateAction;
use Xelqun\Panels\Resources\Resource;
use Xelqun\Panels\Resources\Tab;
use Xelqun\Panels\Tests\Fixtures\Task;
use Xelqun\Tables\Columns\TextColumn;

class TaskResource extends Resource
{
    protected static string $model = Task::class;

    protected static ?string $recordTitleAttribute = 'title';

    public static bool $groupsBulkActions = false;

    public static bool $showsInNavigation = true;

    public static function shouldRegisterNavigation(): bool
    {
        return static::$showsInNavigation;
    }

    public static function form(): array
    {
        return [TextInput::make('title')->required()];
    }

    public static function columns(): array
    {
        return [TextColumn::make('title'), TextColumn::make('slug')->hidden()];
    }

    public static function getHeaderActions(): array
    {
        return [ExportAction::for(static::class)];
    }

    public static function getTabs(): array
    {
        return ['all' => Tab::make(), 'sluggy' => Tab::make()->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('slug'))];
    }

    public static function bulkActions(): array
    {
        return static::$groupsBulkActions ? [ActionGroup::make(parent::bulkActions())] : parent::bulkActions();
    }

    public static function recordActions(): array
    {
        return [
            ...parent::recordActions(),
            ReplicateAction::for(static::class)
                ->excludeAttributes(['slug'])
                ->beforeReplicaSaved(fn (Model $replica) => $replica->setAttribute('title', $replica->getAttribute('title').' (copy)')),
        ];
    }
}
