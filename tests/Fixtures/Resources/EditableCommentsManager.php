<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

use Xelqun\Panels\Resources\RelationManager;
use Xelqun\Tables\Columns\TextInputColumn;

class EditableCommentsManager extends RelationManager
{
    protected static string $relationship = 'comments';

    public static function form(): array
    {
        return [];
    }

    public static function columns(): array
    {
        return [TextInputColumn::make('body')->authorize(true)];
    }
}
