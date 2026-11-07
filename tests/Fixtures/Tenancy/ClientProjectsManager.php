<?php

namespace Xelqun\Panels\Tests\Fixtures\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Panels\Actions\AssociateAction;
use Xelqun\Panels\Resources\RelationManager;
use Xelqun\Tables\Columns\TextColumn;

class ClientProjectsManager extends RelationManager
{
    protected static string $relationship = 'projects';

    public static function form(): array
    {
        return [TextInput::make('name')->required()];
    }

    public static function columns(): array
    {
        return [TextColumn::make('name')];
    }

    public static function headerActions(Model $owner): array
    {
        return [...parent::headerActions($owner), AssociateAction::for(static::class, $owner)];
    }
}
