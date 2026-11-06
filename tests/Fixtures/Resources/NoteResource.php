<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Panels\Resources\Pages\ManageRecords;
use Xelqun\Panels\Resources\Resource;
use Xelqun\Panels\Tests\Fixtures\Note;
use Xelqun\Tables\Columns\TextColumn;

/**
 * Everything on one page.
 */
class NoteResource extends Resource
{
    protected static string $model = Note::class;

    protected static ?string $recordTitleAttribute = 'name';

    /** @var array<int, string> */
    public static array $calls = [];

    public static function form(): array
    {
        return [TextInput::make('name')->required()];
    }

    public static function columns(): array
    {
        return [TextColumn::make('name')];
    }

    public static function getPages(): array
    {
        return ['index' => ManageRecords::class];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('name', '!=', 'Hidden');
    }

    public static function afterSave(Model $record, array $data): void
    {
        self::$calls[] = 'afterSave';
    }

    public static function afterCreate(Model $record, array $data): void
    {
        self::$calls[] = 'afterCreate';
    }
}
