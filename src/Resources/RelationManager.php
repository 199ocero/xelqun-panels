<?php

namespace Xelqun\Panels\Resources;

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
     * @return array<int, Action|ActionGroup>
     */
    public static function headerActions(Model $owner): array
    {
        $label = static::getModelLabel($owner);

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
     * @return array<int, BulkAction>
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
        $record = static::saveRecord($relation instanceof HasOneOrMany ? $relation->make() : $relation->getRelated()->newInstance(), $data);

        if ($relation instanceof BelongsToMany) {
            $relation->attach($record);
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
