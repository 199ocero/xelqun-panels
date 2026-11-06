<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

use Xelqun\Forms\Components\TextInput;
use Xelqun\Panels\Resources\RelationManager;
use Xelqun\Tables\Columns\TextColumn;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    public static function form(): array
    {
        return [TextInput::make('body')->required()->maxLength(20)];
    }

    public static function columns(): array
    {
        return [TextColumn::make('body')->searchable()];
    }
}
