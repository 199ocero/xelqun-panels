<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Panels\Actions\AssociateAction;
use Xelqun\Panels\Actions\DissociateAction;
use Xelqun\Panels\Resources\RelationManager;
use Xelqun\Tables\Columns\TextColumn;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static string $recordTitleAttribute = 'body';

    public static function headerActions(Model $owner): array
    {
        return [...parent::headerActions($owner), AssociateAction::for(static::class, $owner)];
    }

    public static function recordActions(Model $owner): array
    {
        return [...parent::recordActions($owner), DissociateAction::for(static::class, $owner)];
    }

    public static function form(): array
    {
        return [TextInput::make('body')->required()->maxLength(20)];
    }

    public static function columns(): array
    {
        return [TextColumn::make('body')->searchable()];
    }
}
