<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

use Xelqun\Forms\Components\TextInput;
use Xelqun\Panels\Resources\Resource;
use Xelqun\Panels\Tests\Fixtures\Unguarded;
use Xelqun\Tables\Columns\TextColumn;

class UnguardedResource extends Resource
{
    protected static string $model = Unguarded::class;

    public static function getGloballySearchableAttributes(): array
    {
        return ['title'];
    }

    public static function form(): array
    {
        return [TextInput::make('title')];
    }

    public static function columns(): array
    {
        return [TextColumn::make('title')];
    }
}
