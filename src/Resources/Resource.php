<?php

namespace Xelqun\Panels\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Xelqun\Actions\Action;
use Xelqun\Actions\ActionGroup;
use Xelqun\Forms\Components\Component;
use Xelqun\Panels\Navigation\NavigationItem;
use Xelqun\Panels\Panel;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Resources\Pages\CreateRecord;
use Xelqun\Panels\Resources\Pages\EditRecord;
use Xelqun\Panels\Resources\Pages\ListRecords;
use Xelqun\Panels\Resources\Pages\ViewRecord;
use Xelqun\Tables\Actions\BulkAction;
use Xelqun\Tables\Columns\Column;
use Xelqun\Tables\Filters\BaseFilter;
use Xelqun\Tables\Table;

/**
 * Everything a panel needs to manage one model, in one file. List it in
 * Panel::resources().
 *
 * Access goes through the model's policy: viewAny, view, create, update and
 * delete. With no policy, or no such method, the answer is no.
 *
 * @api
 */
abstract class Resource
{
    /** @var class-string<Model> */
    protected static string $model;

    protected static ?string $slug = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    /** The attribute that names a record in titles. */
    protected static ?string $recordTitleAttribute = null;

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = null;

    protected static int $navigationSort = 0;

    /**
     * @return array<int, Component>
     */
    abstract public static function form(): array;

    /**
     * @return array<int, Column>
     */
    abstract public static function columns(): array;

    /**
     * @return array<int, BaseFilter>
     */
    public static function filters(): array
    {
        return [];
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    public static function recordActions(): array
    {
        return [
            Action::make('edit')
                ->label(__('xelqun-panels::resources.edit_action'))
                ->icon('pencil')
                ->iconButton()
                ->authorize('update')
                ->url(fn (Model $record): string => static::getUrl('edit', ['record' => $record])),
            Action::make('delete')
                ->label(__('xelqun-panels::resources.delete'))
                ->icon('trash')
                ->iconButton()
                ->color('danger')
                ->authorize('delete')
                ->requiresConfirmation()
                ->successNotificationTitle(__('xelqun-panels::resources.deleted', ['label' => Str::ucfirst(static::getModelLabel())]))
                ->action(fn (Model $record) => $record->delete()),
        ];
    }

    /**
     * @return array<int, BulkAction>
     */
    public static function bulkActions(): array
    {
        return [
            BulkAction::make('delete')
                ->label(__('xelqun-panels::resources.delete_selected'))
                ->color('danger')
                ->authorize('delete')
                ->requiresConfirmation()
                ->successNotificationTitle(__('xelqun-panels::resources.deleted_many'))
                ->action(fn (Collection $records) => $records->each->delete()),
        ];
    }

    /**
     * Change the table's settings, such as its default sort.
     */
    public static function configureTable(Table $table): void {}

    /**
     * Every record this resource can reach. This is the security boundary: a
     * record it leaves out is a 404 on every page and action.
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        return static::$model::query();
    }

    /**
     * Swap a page class here to customize that page.
     *
     * @return array{index: class-string<ListRecords>, create: class-string<CreateRecord>, view: class-string<ViewRecord>, edit: class-string<EditRecord>}
     */
    public static function getPages(): array
    {
        return [
            'index' => ListRecords::class,
            'create' => CreateRecord::class,
            'view' => ViewRecord::class,
            'edit' => EditRecord::class,
        ];
    }

    /**
     * Adjust attributes before they are written.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function mutateBeforeSave(array $attributes): array
    {
        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function beforeCreate(array $data): void {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function afterCreate(Model $record, array $data): void {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function beforeSave(Model $record, array $data): void {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function afterSave(Model $record, array $data): void {}

    /**
     * @return class-string<Model>
     */
    public static function getModel(): string
    {
        return static::$model;
    }

    public static function getModelLabel(): string
    {
        return static::$modelLabel ?? Str::lower(Str::headline(class_basename(static::$model)));
    }

    public static function getPluralModelLabel(): string
    {
        return static::$pluralModelLabel ?? Str::plural(static::getModelLabel());
    }

    public static function getRecordTitle(Model $record): string
    {
        return (string) (static::$recordTitleAttribute === null ? $record->getKey() : $record->getAttribute(static::$recordTitleAttribute));
    }

    public static function getSlug(): string
    {
        return static::$slug ?? Str::plural(Str::kebab(class_basename(static::$model)));
    }

    /**
     * Whether the current user can do this, by the model's policy.
     */
    public static function can(string $ability, ?Model $record = null): bool
    {
        return Gate::allows($ability, $record ?? static::$model);
    }

    public static function hasPolicy(): bool
    {
        return Gate::getPolicyFor(static::$model) !== null;
    }

    /**
     * Find a record through the resource's query, so nothing else is reachable.
     */
    public static function findRecord(int|string $key): Model
    {
        return static::getEloquentQuery()->whereKey($key)->firstOrFail();
    }

    /**
     * Models become their primary key, because records are found by it and a
     * model's custom route key would point somewhere else.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function getUrl(string $page = 'index', array $parameters = [], ?Panel $panel = null): string
    {
        $panel ??= app(Panels::class)->current();
        $slug = static::getSlug();

        return route($panel->routeName("resources.{$slug}.{$page}"), array_map(
            fn (mixed $value): mixed => $value instanceof Model ? $value->getKey() : $value,
            $parameters,
        ));
    }

    public static function getNavigationBadge(): string|int|null
    {
        return null;
    }

    public static function getNavigationItem(Panel $panel): NavigationItem
    {
        return NavigationItem::make(Str::headline(static::getPluralModelLabel()))
            ->icon(static::$navigationIcon)
            ->group(static::$navigationGroup)
            ->sort(static::$navigationSort)
            ->url(static::getUrl(panel: $panel))
            ->badge(static::getNavigationBadge())
            ->active(request()->routeIs($panel->routeName('resources.'.static::getSlug().'.*')));
    }
}
