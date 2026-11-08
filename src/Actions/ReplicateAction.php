<?php

namespace Xelqun\Panels\Actions;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Xelqun\Actions\Action;

/**
 * Copies a record. Needs `replicate` in the policy. Relationships aren't copied.
 *
 * @api
 */
class ReplicateAction extends Action
{
    /** @var array<int, string> */
    protected array $excludedAttributes = [];

    protected ?Closure $beforeReplicaSaved = null;

    /**
     * @param  class-string<\Xelqun\Panels\Resources\Resource>  $resource
     */
    public static function for(string $resource): static
    {
        $action = static::make('replicate')
            ->label(__('xelqun-panels::resources.replicate'))
            ->icon('copy')
            ->authorize('replicate')
            ->requiresConfirmation()
            ->successNotificationTitle(__('xelqun-panels::resources.replicated', ['label' => Str::ucfirst($resource::getModelLabel())]));

        return $action->action(function (Model $record) use ($action, $resource): Model {
            $replica = $record->replicate($action->excludedAttributes);

            if ($action->beforeReplicaSaved !== null) {
                ($action->beforeReplicaSaved)($replica, $record);
            }

            $resource::assignToTenant($replica);
            $replica->save();
            $resource::attachToTenant($replica);

            return $replica;
        });
    }

    /**
     * Attributes that must not be copied, such as a unique slug.
     *
     * @param  array<int, string>  $attributes
     */
    public function excludeAttributes(array $attributes): static
    {
        $this->excludedAttributes = $attributes;

        return $this;
    }

    /**
     * @template TModel of Model
     *
     * @param  Closure(TModel, TModel): mixed  $using  Gets the copy, then the original.
     */
    public function beforeReplicaSaved(Closure $using): static
    {
        $this->beforeReplicaSaved = $using;

        return $this;
    }
}
