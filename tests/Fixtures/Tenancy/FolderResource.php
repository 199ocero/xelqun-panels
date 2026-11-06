<?php

namespace Xelqun\Panels\Tests\Fixtures\Tenancy;

use Xelqun\Forms\Components\TextInput;
use Xelqun\Panels\Resources\Resource;
use Xelqun\Tables\Columns\TextColumn;

class FolderResource extends Resource
{
    protected static string $model = Folder::class;

    protected static ?string $tenantOwnershipRelationship = 'teams';

    public static function form(): array
    {
        return [TextInput::make('name')->required()];
    }

    public static function columns(): array
    {
        return [TextColumn::make('name')];
    }
}
