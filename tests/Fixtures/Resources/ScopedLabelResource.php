<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

use Illuminate\Database\Eloquent\Builder;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Panels\Resources\Resource;
use Xelqun\Panels\Tests\Fixtures\Label;
use Xelqun\Tables\Columns\TextColumn;

/**
 * A secret label is out of this resource's reach.
 */
class ScopedLabelResource extends Resource
{
    protected static string $model = Label::class;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('name', '!=', 'Secret');
    }

    public static function form(): array
    {
        return [TextInput::make('name')->required()];
    }

    public static function columns(): array
    {
        return [TextColumn::make('name')];
    }
}
