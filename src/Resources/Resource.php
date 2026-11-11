<?php

namespace Xelqun\Panels\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Xelqun\Actions\Action;
use Xelqun\Actions\ActionGroup;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Components\Field;
use Xelqun\Panels\Actions\DeleteAction;
use Xelqun\Panels\Actions\EditAction;
use Xelqun\Panels\Actions\ForceDeleteAction;
use Xelqun\Panels\Actions\ImportColumn;
use Xelqun\Panels\Actions\RestoreAction;
use Xelqun\Panels\Exceptions\PanelDefinitionException;
use Xelqun\Panels\Navigation\Cluster;
use Xelqun\Panels\Navigation\NavigationItem;
use Xelqun\Panels\Pages\Page;
use Xelqun\Panels\Panel;
use Xelqun\Panels\Panels;
use Xelqun\Panels\Resources\Pages\CreateRecord;
use Xelqun\Panels\Resources\Pages\EditRecord;
use Xelqun\Panels\Resources\Pages\ListRecords;
use Xelqun\Panels\Resources\Pages\ManageSingleRecord;
use Xelqun\Panels\Resources\Pages\ViewRecord;
use Xelqun\Panels\Tables\TrashedFilter;
use Xelqun\Panels\Widgets\PanelWidget;
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

    /** The label of another item in its group, to sit under. */
    protected static ?string $navigationParentItem = null;

    protected static int $navigationSort = 0;

    /** @var class-string<Cluster>|null */
    protected static ?string $navigationLabel = null;

    protected static ?string $cluster = null;

    /**
     * Make this resource live under a record of another one, as in
     * /posts/1/comments. It leaves the sidebar and search, and only reaches the
     * parent's related records. A relation manager with $relatedResource set to
     * it links to its pages.
     *
     * Its pages need the parent's `view`, and changes are then up to this
     * resource's own policy. Unlike a relation manager, the parent's `update` isn't
     * asked, so someone can add a comment to a post they can't edit.
     *
     * @var class-string<\Xelqun\Panels\Resources\Resource>|null
     */
    protected static ?string $parentResource = null;

    /** The relationship on the parent. By default, this resource's plural model name. */
    protected static ?string $parentRelationship = null;

    /** Turn this off for a model that isn't owned by a tenant. */
    protected static bool $isScopedToTenant = true;

    /** Overrides the panel's ownership relationship for this model. */
    protected static ?string $tenantOwnershipRelationship = null;

    /** How many records this resource adds to the search results. */
    protected static int $globalSearchResultsLimit = 5;

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
        return static::usesSoftDeletes() ? [TrashedFilter::make('trashed')] : [];
    }

    /**
     * What the view page shows instead of a disabled form. Use TextEntry,
     * IconEntry and ImageEntry, inside any of forms' layouts.
     *
     * @return array<int, Component>
     */
    public static function infolist(): array
    {
        return [];
    }

    /**
     * Tabs above the list, as key => Tab. The first one is the default.
     *
     * @return array<string, Tab>
     */
    public static function getTabs(): array
    {
        return [];
    }

    /**
     * More buttons beside the list's title, such as ExportAction and ImportAction.
     *
     * @return array<int, Action|ActionGroup>
     */
    public static function getHeaderActions(): array
    {
        return [];
    }

    /**
     * What ExportAction writes, as heading => attribute, with dots for a relation.
     * By default, the columns of the table.
     *
     * @return array<string, string>
     */
    public static function getExportColumns(): array
    {
        return collect(static::columns())
            ->reject(fn (Column $column): bool => $column->isHidden())
            ->mapWithKeys(fn (Column $column): array => [$column->getLabel() => $column->getName()])
            ->all();
    }

    /**
     * What ImportAction takes from a file. Anything else in it is ignored, so a
     * file can't set a column you didn't list. By default, the form's top-level
     * fields. Give an ImportColumn for a label, header guesses or rules.
     *
     * @return array<int, string|ImportColumn>
     */
    public static function getImportColumns(): array
    {
        return collect(static::form())
            ->flatMap(fn (Component $component): array => $component instanceof Field ? [$component->getName()] : [])
            ->values()
            ->all();
    }

    /**
     * Widgets above the list.
     *
     * @return array<int, class-string<PanelWidget>>
     */
    public static function getHeaderWidgets(): array
    {
        return [];
    }

    /**
     * Widgets on a record's view and edit pages. A Widget reads the record from
     * $this->record.
     *
     * @return array<int, class-string<PanelWidget>>
     */
    public static function getRecordWidgets(): array
    {
        return [];
    }

    /**
     * Relationship tables for the edit and view pages. A RelationGroup puts
     * several under one tab.
     *
     * @return array<int, class-string<RelationManager>|RelationGroup>
     */
    public static function getRelations(): array
    {
        return [];
    }

    /**
     * tabs shows one relationship at a time, and stacked shows them all, one
     * after another.
     */
    public static function getRelationManagersLayout(): string
    {
        return 'tabs';
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    public static function recordActions(): array
    {
        return [
            EditAction::for(static::class),
            DeleteAction::for(static::class),
            ...(static::usesSoftDeletes() ? [RestoreAction::for(static::class), ForceDeleteAction::for(static::class)] : []),
        ];
    }

    /**
     * @return array<int, BulkAction|ActionGroup>
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
            ...(! static::usesSoftDeletes() ? [] : [
                BulkAction::make('restore')
                    ->label(__('xelqun-panels::resources.restore_selected'))
                    ->authorize('restore')
                    ->requiresConfirmation()
                    ->successNotificationTitle(__('xelqun-panels::resources.restored_many'))
                    ->action(fn (Collection $records) => $records->each(static::restoreRecord(...))),
                BulkAction::make('forceDelete')
                    ->label(__('xelqun-panels::resources.force_delete_selected'))
                    ->color('danger')
                    ->authorize('forceDelete')
                    ->requiresConfirmation()
                    ->successNotificationTitle(__('xelqun-panels::resources.deleted_many'))
                    ->action(fn (Collection $records) => $records->filter(static::isTrashed(...))->each->forceDelete()),
            ]),
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
        $panels = app(Panels::class);

        // Records of every tenant would otherwise show. Nothing reaches here like this today, so this is the backstop.
        abort_if(static::$isScopedToTenant && $panels->current()?->hasTenancy() && $panels->tenant() === null, 404);

        return static::isNested()
            ? static::getParentRelation()->getQuery()->select((new static::$model)->qualifyColumn('*'))
            : static::$model::query();
    }

    public static function isNested(): bool
    {
        return static::$parentResource !== null;
    }

    /**
     * @return class-string<\Xelqun\Panels\Resources\Resource>|null
     */
    public static function getParentResource(): ?string
    {
        return static::$parentResource;
    }

    /**
     * Found through the parent resource, so a parent out of reach takes its
     * children with it, and refused the same as a parent that doesn't exist. It is
     * kept on the request once found, because every row asks for it again.
     */
    public static function getParentRecord(): Model
    {
        $key = app(Panels::class)->parentKey();

        abort_if($key === null, 404);

        $cacheKey = 'xelqun-panels.parent.'.static::$parentResource.".{$key}";
        $attributes = request()->attributes;

        if (! $attributes->has($cacheKey)) {
            $parent = static::$parentResource::findRecord($key);

            abort_unless(static::$parentResource::can('view', $parent), 404);

            $attributes->set($cacheKey, $parent);
        }

        return $attributes->get($cacheKey);
    }

    /**
     * @return Relation<Model, Model, mixed>
     */
    public static function getParentRelation(): Relation
    {
        return static::getParentRecord()->{static::$parentRelationship ?? Str::camel(Str::plural(class_basename(static::$model)))}();
    }

    /**
     * The record a ManageSingleRecord page edits. By default that's the first one,
     * or a new one until it's saved.
     */
    public static function getSingularRecord(): Model
    {
        $record = static::getEloquentQuery()->first();

        if ($record !== null) {
            return $record;
        }

        $record = new static::$model;
        static::assignToTenant($record);

        return $record;
    }

    /**
     * Keep this model to the current tenant, for every query in this request:
     * tables, Select options, widgets, scopedUnique() and your own. It reads the
     * tenant as each query runs, so it does nothing outside a tenant, including
     * for later requests under Octane.
     */
    public static function scopeToTenants(Panel $panel): void
    {
        if (! static::$isScopedToTenant) {
            return;
        }

        $relationship = static::getTenantRelationshipName($panel);

        if (! method_exists(static::$model, $relationship)) {
            throw new PanelDefinitionException('['.static::$model."] needs a [{$relationship}] relationship to belong to a tenant. Set \$isScopedToTenant to false if it doesn't.");
        }

        $panelId = $panel->getId();

        // One scope per panel, and only in that panel: a model's scopes outlive the request.
        static::$model::addGlobalScope("xelqun-panels.tenant.{$panelId}", function (Builder $query) use ($relationship, $panelId): void {
            $panels = app(Panels::class);
            $tenant = $panels->tenant();

            if ($tenant === null || $panels->current()?->getId() !== $panelId) {
                return;
            }

            if ($query->getModel()->{$relationship}() instanceof BelongsTo) {
                $query->whereBelongsTo($tenant, $relationship);
            } else {
                $query->whereHas($relationship, fn (Builder $tenants): Builder => $tenants->whereKey($tenant->getKey()));
            }
        });
    }

    /**
     * A new record starts out in the current tenant. A many-to-many has to wait
     * until the record exists: see attachToTenant().
     */
    public static function assignToTenant(Model $record): void
    {
        if (static::isNested()) {
            $relation = static::getParentRelation();

            if ($relation instanceof MorphOneOrMany) {
                $record->setAttribute($relation->getMorphType(), $relation->getMorphClass());
            }

            if ($relation instanceof HasOneOrMany) {
                $record->setAttribute($relation->getForeignKeyName(), $relation->getParentKey());
            }
        }

        $tenant = app(Panels::class)->tenant();

        if ($tenant === null || ! static::$isScopedToTenant) {
            return;
        }

        $relation = $record->{static::getTenantRelationshipName(app(Panels::class)->current())}();

        if ($relation instanceof BelongsTo) {
            $relation->associate($tenant);
        }
    }

    public static function attachToTenant(Model $record): void
    {
        if (static::isNested()) {
            $relation = static::getParentRelation();

            if ($relation instanceof BelongsToMany) {
                $relation->attach($record);
            }
        }

        $tenant = app(Panels::class)->tenant();

        if ($tenant === null || ! static::$isScopedToTenant) {
            return;
        }

        $relation = $record->{static::getTenantRelationshipName(app(Panels::class)->current())}();

        if ($relation instanceof BelongsToMany) {
            $relation->attach($tenant);
        }
    }

    protected static function getTenantRelationshipName(Panel $panel): string
    {
        return static::$tenantOwnershipRelationship ?? $panel->getTenantOwnershipRelationship();
    }

    /**
     * Swap a page class here to customize that page, or return only `index` with
     * ManageRecords to do everything on one page, or ManageSingleRecord for a
     * resource that's a single record.
     *
     * Add pages of your own under any other key, such as a ResourcePage or a
     * ManageRelatedRecords. Each gets a route, and getUrl('key') links to it.
     *
     * @return array<string, class-string<Page>>
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
     * Whether the resource has a link in the navigation. It still needs viewAny.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive(static::$model), true);
    }

    public static function getDeletedAtColumn(): string
    {
        $model = new static::$model;

        return $model->qualifyColumn(method_exists($model, 'getDeletedAtColumn') ? $model->getDeletedAtColumn() : 'deleted_at');
    }

    public static function isTrashed(?Model $record): bool
    {
        return static::usesSoftDeletes() && $record?->exists && $record->getAttribute(Str::afterLast(static::getDeletedAtColumn(), '.')) !== null;
    }

    public static function restoreRecord(Model $record): void
    {
        if (method_exists($record, 'restore')) {
            $record->restore();
        }
    }

    public static function hasPage(string $page): bool
    {
        return isset(static::getPages()[$page]);
    }

    /**
     * Where a row of this resource leads: edit if the viewer can, or else view.
     */
    public static function getRecordUrl(Model $record): ?string
    {
        return match (true) {
            // Pages only find records that aren't trashed.
            static::isTrashed($record) => null,
            static::hasPage('edit') && static::can('update', $record) => static::getUrl('edit', ['record' => $record]),
            static::hasPage('view') && static::can('view', $record) => static::getUrl('view', ['record' => $record]),
            default => null,
        };
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
     * The slug, after its cluster's slug when it has one.
     */
    public static function getPathInPanel(): string
    {
        $cluster = static::getCluster();

        return $cluster === null ? static::getSlug() : $cluster::getSlug().'/'.static::getSlug();
    }

    /**
     * What each of its route names starts with, as in resources.settings.countries.
     * Dots, because a host's Wayfinder can't put a slash in a name.
     */
    public static function getRouteBaseName(): string
    {
        return 'resources.'.str_replace('/', '.', static::getPathInPanel());
    }

    /**
     * The columns the ⌘K palette looks in, with `author.name` to reach through a
     * relation. By default that's the record title, so a title that's an accessor
     * needs this overridden. Return nothing to keep a resource out of search.
     *
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return static::$recordTitleAttribute === null ? [] : [static::$recordTitleAttribute];
    }

    /**
     * What a search result shows under its title, as label => value. Add any
     * relation this reads to getEloquentQuery(), or each result runs a query.
     *
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [];
    }

    /**
     * Links under a search result, such as one to edit the record. Each needs a url().
     *
     * @return array<int, NavigationItem>
     */
    public static function getGlobalSearchResultActions(Model $record): array
    {
        return [];
    }

    /**
     * Matching records the current user may view. Only getEloquentQuery() can
     * bring them back, and the policy sees each one before it leaves.
     *
     * shortcut: the policy sees four times the limit, so one that refuses more than that can hide records from search. Page through matches if an app's policies are that narrow.
     *
     * @return array<int, array{title: string, url: string, details: string, actions?: array<int, array{label: string, url: string}>}>
     */
    public static function getGlobalSearchResults(string $search): array
    {
        $attributes = static::getGloballySearchableAttributes();

        if ($attributes === []) {
            return [];
        }

        // shortcut: % and _ in a search act as wildcards, as they do in table search.
        $pattern = "%{$search}%";

        return static::getEloquentQuery()
            // Grouped, or an OR here would step outside the resource's own scope.
            ->where(function (Builder $query) use ($attributes, $pattern): void {
                foreach ($attributes as $attribute) {
                    if (str_contains($attribute, '.')) {
                        $query->orWhereHas(
                            Str::beforeLast($attribute, '.'),
                            fn (Builder $related): Builder => $related->whereLike($related->qualifyColumn(Str::afterLast($attribute, '.')), $pattern),
                        );
                    } else {
                        $query->orWhereLike($query->qualifyColumn($attribute), $pattern);
                    }
                }
            })
            ->limit(static::$globalSearchResultsLimit * 4)
            ->get()
            ->filter(fn (Model $record): bool => static::can('view', $record))
            ->take(static::$globalSearchResultsLimit)
            ->map(fn (Model $record): array => [
                'title' => static::getRecordTitle($record),
                'url' => static::hasPage('view') ? static::getUrl('view', ['record' => $record]) : static::getUrl(),
                'details' => collect(static::getGlobalSearchResultDetails($record))
                    ->map(fn (?string $value, string $label): string => "{$label}: {$value}")
                    ->implode(' · '),
                ...(($actions = static::getGlobalSearchResultActions($record)) === [] ? [] : [
                    'actions' => array_values(array_filter(array_map(
                        fn (NavigationItem $action): ?array => $action->getUrl() === null ? null : ['label' => $action->getLabel(), 'url' => $action->getUrl()],
                        $actions,
                    ))),
                ]),
            ])
            ->values()
            ->all();
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
        // MySQL would read "5abc" as 5.
        abort_if((new (static::$model))->getKeyType() === 'int' && ! ctype_digit((string) $key), 404);

        return static::getEloquentQuery()->whereKey($key)->firstOrFail();
    }

    /**
     * Models become their primary key, because records are found by it and a
     * model's custom route key would point somewhere else. A nested resource
     * outside its parent's pages needs `parentRecord`.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function getUrl(string $page = 'index', array $parameters = [], ?Panel $panel = null): string
    {
        $panel ??= app(Panels::class)->current() ?? self::findOnlyPanel();

        if (static::isNested()) {
            $parameters['parentRecord'] ??= app(Panels::class)->parentKey();
        }

        return route($panel->routeName(static::getRouteBaseName().".{$page}"), array_map(
            fn (mixed $value): mixed => $value instanceof Model ? $value->getKey() : $value,
            $parameters,
        ));
    }

    /**
     * Outside a panel's request, as in a job, a mail or a test, the panel that
     * registers this resource, when only one does.
     */
    private static function findOnlyPanel(): Panel
    {
        $panels = array_values(array_filter(
            app(Panels::class)->all(),
            fn (Panel $panel): bool => in_array(static::class, $panel->getResources(), true),
        ));

        if (count($panels) !== 1) {
            throw new PanelDefinitionException('['.static::class.'] is in '.count($panels).' panels, so getUrl() needs to be told which: getUrl($page, $parameters, app(Panels::class)->get(\'admin\')).');
        }

        return $panels[0];
    }

    /**
     * Tabs across a record's pages, such as view, edit and a nested list.
     *
     * @return array<int, NavigationItem>
     */
    public static function getRecordSubNavigation(Model $record): array
    {
        return [];
    }

    /**
     * primary, danger, info, success, warning or gray, for the badge.
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return null;
    }

    public static function getNavigationBadge(): string|int|null
    {
        return null;
    }

    /**
     * @return class-string<Cluster>|null
     */
    public static function getCluster(): ?string
    {
        return static::$cluster;
    }

    public static function getNavigationIcon(): ?string
    {
        return static::$navigationIcon;
    }

    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel ?? Str::headline(static::getPluralModelLabel());
    }

    public static function getNavigationItem(Panel $panel): NavigationItem
    {
        return NavigationItem::make(static::getNavigationLabel())
            ->icon(static::getNavigationIcon())
            ->group(static::$navigationGroup)
            ->sort(static::$navigationSort)
            ->url(static::getUrl(panel: $panel))
            ->badge(static::getNavigationBadge())
            ->badgeColor(static::getNavigationBadgeColor())
            ->badgeTooltip(static::getNavigationBadgeTooltip())
            ->parentItem(static::$navigationParentItem)
            ->active(request()->routeIs($panel->routeName(static::getRouteBaseName().'.*')));
    }
}
