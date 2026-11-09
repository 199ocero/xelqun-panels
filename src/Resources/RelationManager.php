<?php

namespace Xelqun\Panels\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use LogicException;
use Xelqun\Actions\Action;
use Xelqun\Actions\ActionGroup;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Eloquent\ModelPersister;
use Xelqun\Forms\Form;
use Xelqun\Forms\Uploads\UploadBatch;
use Xelqun\Panels\Panels;
use Xelqun\Tables\Actions\BulkAction;
use Xelqun\Tables\Columns\Column;
use Xelqun\Tables\Filters\BaseFilter;
use Xelqun\Tables\Table;

/**
 * A table of one relationship, under a record's edit and view pages, with create
 * and edit in modals. List it in Resource::getRelations().
 *
 * Set $relatedResource to reuse a resource's form, columns and filters, or
 * override them here. Access goes through the related model's policy, and the
 * parent's `view`. Changing anything also takes the parent's `update`, and a view
 * page is read-only.
 *
 * Add AttachAction and DetachAction for a many-to-many, or AssociateAction and
 * DissociateAction for a has-many, to link records that already exist.
 *
 * @api
 */
abstract class RelationManager
{
    /** The relationship's method on the parent model. */
    protected static string $relationship;

    protected static ?string $title = null;

    /** @var class-string<\Xelqun\Panels\Resources\Resource>|null */
    protected static ?string $relatedResource = null;

    /** The column that names a record in the attach and associate modals. */
    protected static string $recordTitleAttribute = 'name';

    /** Load the rows after the page renders, for a slow relationship. */
    protected static bool $isLazy = false;

    /**
     * @return array<int, Component>
     */
    public static function form(): array
    {
        return static::getRelatedResource()::form();
    }

    /**
     * @return array<int, Column>
     */
    public static function columns(): array
    {
        return static::getRelatedResource()::columns();
    }

    /**
     * @return array<int, BaseFilter>
     */
    public static function filters(): array
    {
        return static::$relatedResource === null ? [] : static::$relatedResource::filters();
    }

    public static function configureTable(Table $table): void {}

    /**
     * A count or short word on the tab. It gets the record that owns the relationship.
     */
    public static function getBadge(Model $owner): string|int|null
    {
        return null;
    }

    /**
     * primary, danger, info, success, warning or gray, for the badge.
     */
    public static function getBadgeColor(Model $owner): ?string
    {
        return null;
    }

    /**
     * Whether the table can't change anything. Null follows the page: read-only
     * on the view page, and not on the edit page.
     */
    public static function isReadOnly(): ?bool
    {
        return null;
    }

    public static function isLazy(): bool
    {
        return static::$isLazy;
    }

    /**
     * A nested related resource's page for a row, where it has one.
     */
    public static function getRecordUrl(Model $owner, Model $record): ?string
    {
        $resource = static::$relatedResource;

        if ($resource === null || ! $resource::isNested()) {
            return null;
        }

        $page = match (true) {
            $resource::hasPage('edit') && $resource::can('update', $record) => 'edit',
            $resource::hasPage('view') && $resource::can('view', $record) => 'view',
            default => null,
        };

        return $page === null ? null : $resource::getUrl($page, ['parentRecord' => $owner, 'record' => $record]);
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    public static function headerActions(Model $owner): array
    {
        $label = static::getModelLabel($owner);
        $nested = static::$relatedResource !== null && static::$relatedResource::isNested() ? static::$relatedResource : null;

        if ($nested !== null) {
            return [
                Action::make('create')
                    ->label(__('xelqun-panels::resources.new', ['label' => $label]))
                    ->icon('plus')
                    ->authorize('create')
                    ->url(fn (): string => $nested::getUrl('create', ['parentRecord' => $owner])),
            ];
        }

        return [
            Action::make('create')
                ->label(__('xelqun-panels::resources.new', ['label' => $label]))
                ->icon('plus')
                ->authorize('create')
                ->schema(fn (): array => static::form())
                ->modalSubmitActionLabel(__('xelqun-panels::resources.create', ['label' => $label]))
                ->successNotificationTitle(__('xelqun-panels::resources.created', ['label' => Str::ucfirst($label)]))
                ->action(fn (array $data): Model => static::createRecord($owner, $data)),
        ];
    }

    /**
     * @return array<int, Action|ActionGroup>
     */
    public static function recordActions(Model $owner): array
    {
        $label = Str::ucfirst(static::getModelLabel($owner));

        return [
            Action::make('edit')
                ->label(__('xelqun-panels::resources.edit_action'))
                ->icon('pencil')
                ->iconButton()
                ->authorize('update')
                ->schema(fn (): array => static::form())
                ->modalSubmitActionLabel(__('xelqun-panels::resources.save'))
                ->successNotificationTitle(__('xelqun-panels::resources.saved', ['label' => $label]))
                ->action(fn (array $data, Model $record): Model => static::saveRecord($record, $data)),
            Action::make('delete')
                ->label(__('xelqun-panels::resources.delete'))
                ->icon('trash')
                ->iconButton()
                ->color('danger')
                ->authorize('delete')
                ->requiresConfirmation()
                ->successNotificationTitle(__('xelqun-panels::resources.deleted', ['label' => $label]))
                ->action(fn (Model $record) => $record->delete()),
        ];
    }

    /**
     * @return array<int, BulkAction|ActionGroup>
     */
    public static function bulkActions(Model $owner): array
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

    public static function getTitle(): string
    {
        return static::$title ?? Str::headline(static::$relationship);
    }

    public static function getRecordTitleAttribute(): string
    {
        return static::$recordTitleAttribute;
    }

    /**
     * @return Relation<Model, Model, mixed>
     */
    public static function getRelation(Model $owner): Relation
    {
        return $owner->{static::$relationship}();
    }

    /**
     * Where to look for records to link. A resource's query is the security
     * boundary, so a related model with a resource goes through it.
     *
     * shortcut: a related model with no resource in the panel isn't kept to the tenant here. Give it a resource if its rows belong to a tenant.
     *
     * @return Builder<Model>
     */
    public static function getLinkableQuery(Model $owner): Builder
    {
        $resource = static::findRelatedResource($owner);

        return $resource === null || $resource::isNested()
            ? static::getRelation($owner)->getRelated()->newQuery()
            : $resource::getEloquentQuery();
    }

    /**
     * @return class-string<\Xelqun\Panels\Resources\Resource>|null
     */
    protected static function findRelatedResource(Model $owner): ?string
    {
        $model = static::getRelation($owner)->getRelated()::class;

        // $relatedResource may only be lending its form to another model.
        return collect([static::$relatedResource, ...(app(Panels::class)->current()?->getResources() ?? [])])
            ->filter()
            ->first(fn (string $resource): bool => $resource::getModel() === $model);
    }

    public static function canViewFor(Model $owner): bool
    {
        return Gate::allows('viewAny', static::getRelation($owner)->getRelated()::class);
    }

    protected static function getModelLabel(Model $owner): string
    {
        return Str::lower(Str::headline(class_basename(static::getRelation($owner)->getRelated())));
    }

    /**
     * @param  array<string, mixed>  $data  Validated by the modal.
     */
    protected static function createRecord(Model $owner, array $data): Model
    {
        $relation = static::getRelation($owner);
        $resource = static::findRelatedResource($owner);
        $record = $relation instanceof HasOneOrMany ? $relation->make() : $relation->getRelated()->newInstance();

        if ($resource !== null && ! $resource::isNested()) {
            $resource::assignToTenant($record);
        }

        static::saveRecord($record, $data);

        if ($relation instanceof BelongsToMany) {
            $relation->attach($record);
        }

        if ($resource !== null && ! $resource::isNested()) {
            $resource::attachToTenant($record);
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data  Validated by the modal.
     */
    protected static function saveRecord(Model $record, array $data): Model
    {
        // The same code as a full form, so nested fields and uploads save too.
        $form = Form::make(static::form());
        $form->setRecord($record);

        return app(ModelPersister::class)->save($form, $data, app(UploadBatch::class));
    }

    /**
     * @return class-string<\Xelqun\Panels\Resources\Resource>
     */
    protected static function getRelatedResource(): string
    {
        return static::$relatedResource ?? throw new LogicException('['.static::class.'] needs $relatedResource, or its own form() and columns().');
    }
}
