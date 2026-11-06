<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Panels\Resources\Resource;
use Xelqun\Panels\Tests\Fixtures\Post;
use Xelqun\Tables\Columns\TextColumn;
use Xelqun\Tables\Filters\SelectFilter;
use Xelqun\Tables\Table;

class PostResource extends Resource
{
    protected static string $model = Post::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationIcon = 'file-text';

    protected static ?string $navigationGroup = 'Content';

    /** @var array<int, string> */
    public static array $calls = [];

    public static function form(): array
    {
        return [
            TextInput::make('title')->required()->maxLength(50),
            Repeater::make('tags')->schema([TextInput::make('name')->required()]),
            FileUpload::make('cover')->disk('local'),
        ];
    }

    public static function columns(): array
    {
        return [TextColumn::make('title')->searchable()->sortable()];
    }

    public static function filters(): array
    {
        return [SelectFilter::make('status')->options(['live' => 'Live', 'draft' => 'Draft'])];
    }

    public static function configureTable(Table $table): void
    {
        $table->defaultSort('title');
    }

    /**
     * Only the red team's posts. Anything else is out of reach.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('team', 'red');
    }

    public static function mutateBeforeSave(array $attributes): array
    {
        return [...$attributes, 'title' => trim((string) $attributes['title'])];
    }

    public static function beforeCreate(array $data): void
    {
        self::$calls[] = 'beforeCreate';
    }

    public static function afterCreate(Model $record, array $data): void
    {
        self::$calls[] = 'afterCreate';
    }

    public static function beforeSave(Model $record, array $data): void
    {
        self::$calls[] = 'beforeSave';
    }

    public static function afterSave(Model $record, array $data): void
    {
        self::$calls[] = 'afterSave';
    }
}
