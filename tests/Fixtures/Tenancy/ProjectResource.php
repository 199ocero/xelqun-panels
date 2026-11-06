<?php

namespace Xelqun\Panels\Tests\Fixtures\Tenancy;

use Xelqun\Forms\Components\TextInput;
use Xelqun\Panels\Resources\Resource;
use Xelqun\Tables\Columns\TextColumn;

class ProjectResource extends Resource
{
    protected static string $model = Project::class;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(): array
    {
        return [TextInput::make('name')->required()->scopedUnique()];
    }

    public static function columns(): array
    {
        return [TextColumn::make('name')->searchable()];
    }
}
