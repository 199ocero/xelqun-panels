<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Components\FileUpload;
use Xelqun\Forms\Components\Repeater;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Forms\Layouts\Section;
use Xelqun\Panels\Actions\ExportAction;
use Xelqun\Panels\Infolists\IconEntry;
use Xelqun\Panels\Infolists\TextEntry;
use Xelqun\Panels\Navigation\NavigationItem;
use Xelqun\Panels\Resources\Resource;
use Xelqun\Panels\Resources\Tab;
use Xelqun\Panels\Tests\Fixtures\Post;
use Xelqun\Panels\Widgets\PanelWidget;
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

    public static bool $usesInfolist = false;

    /** @var array<int, class-string<PanelWidget>> */
    public static array $headerWidgets = [];

    public static function getHeaderActions(): array
    {
        return [ExportAction::for(static::class)];
    }

    public static function getHeaderWidgets(): array
    {
        return self::$headerWidgets;
    }

    public static function getRecordWidgets(): array
    {
        return self::$headerWidgets;
    }

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

    public static function getPages(): array
    {
        return [...parent::getPages(), 'sort' => SortPosts::class, 'comments' => ManagePostComments::class];
    }

    public static bool $hasSearchActions = false;

    public static function getGlobalSearchResultActions(Model $record): array
    {
        return static::$hasSearchActions ? [
            NavigationItem::make('Edit')->url(static::getUrl('edit', ['record' => $record])),
            NavigationItem::make('Bad')->url('javascript:alert(1)'),
        ] : [];
    }

    public static function getRelations(): array
    {
        return [CommentsRelationManager::class, LabelsRelationManager::class];
    }

    public static function getTabs(): array
    {
        return [
            'all' => Tab::make(),
            'live' => Tab::make('Published')->icon('zap')->badge(fn (): int => Post::where('status', 'live')->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'live')->orWhere('status', 'scheduled')),
        ];
    }

    public static function infolist(): array
    {
        return self::$usesInfolist ? [
            Section::make('About')->schema([
                TextEntry::make('title')->badge()->color('success'),
                TextEntry::make('author.name')->label('Written by'),
                TextEntry::make('created_at')->date(),
                IconEntry::make('published')->boolean()->state(fn (Post $record): bool => $record->status === 'live'),
            ]),
        ] : [];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'status', 'author.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ['Status' => $record->getAttribute('status')];
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
