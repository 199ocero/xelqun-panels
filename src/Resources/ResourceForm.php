<?php

namespace Xelqun\Panels\Resources;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Xelqun\Forms\Eloquent\ModelPersister;
use Xelqun\Forms\Form;
use Xelqun\Forms\Uploads\UploadBatch;

/**
 * Forms are classes, because a closure can't wait in the browser. This one
 * stands in for every resource's form, and its token says which.
 *
 * @internal
 */
final class ResourceForm extends Form
{
    public const string CREATE = 'create';

    public const string EDIT = 'edit';

    public const string VIEW = 'view';

    /**
     * @param  class-string<\Xelqun\Panels\Resources\Resource>  $resource
     */
    public static function for(string $resource, string $operation, ?Model $record = null): self
    {
        $form = self::make($record, ['resource' => $resource, 'operation' => $operation]);

        return $operation === self::VIEW ? $form->disabled() : $form;
    }

    public function schema(): array
    {
        $resource = $this->getResource();
        $infolist = $this->getOperation() === self::VIEW ? $resource::infolist() : [];

        return $infolist === [] ? $resource::form() : $infolist;
    }

    /**
     * Save what a modal already validated, with this form's hooks and nested fields.
     *
     * @param  array<string, mixed>  $data
     */
    public function persist(array $data): Model
    {
        return app(ModelPersister::class)->save($this, $data, app(UploadBatch::class));
    }

    public function model(): string
    {
        return $this->getResource()::getModel();
    }

    /**
     * What a create form saves into, already in the current tenant, so the tenant
     * never comes from the browser.
     */
    public function getModelInstance(): Model
    {
        $model = parent::getModelInstance();

        if (! $model->exists) {
            $this->getResource()::assignToTenant($model);
        }

        return $model;
    }

    public function resolveRecord(int|string $key): Model
    {
        return $this->getResource()::findRecord($key);
    }

    public function authorize(?Authenticatable $user, ?Model $record): bool
    {
        return $this->getResource()::can(match ($this->getOperation()) {
            self::CREATE => 'create',
            self::VIEW => 'view',
            default => 'update',
        }, $record);
    }

    public function getId(): string
    {
        return $this->getResource()::getSlug().'-form';
    }

    public function getUploadScope(): string
    {
        return parent::getUploadScope().'>'.$this->getResource();
    }

    public function mutateBeforeSave(array $attributes): array
    {
        return $this->getResource()::mutateBeforeSave($attributes);
    }

    public function beforeSave(array $data): void
    {
        $resource = $this->getResource();
        $record = $this->getRecord();

        if ($record?->exists) {
            $resource::beforeSave($record, $data);
        } else {
            $resource::beforeCreate($data);
        }
    }

    public function afterSave(Model $record, array $data): void
    {
        $resource = $this->getResource();

        // A field for the tenant's or parent's key, or a relationship Select for it, could have moved the record.
        $resource::assignToTenant($record);

        if ($record->isDirty()) {
            $record->save();
        }

        if ($this->getOperation() === self::CREATE) {
            $resource::attachToTenant($record);
            $resource::afterCreate($record, $data);
        } else {
            $resource::afterSave($record, $data);
        }
    }

    /**
     * @return class-string<\Xelqun\Panels\Resources\Resource>
     */
    private function getResource(): string
    {
        $resource = $this->context['resource'] ?? null;

        if (! is_string($resource) || ! is_subclass_of($resource, Resource::class)) {
            throw new LogicException('A resource form needs its resource.');
        }

        return $resource;
    }
}
