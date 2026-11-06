<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

use Illuminate\Database\Eloquent\Model;
use Xelqun\Panels\Actions\AttachAction;
use Xelqun\Panels\Actions\DetachAction;
use Xelqun\Panels\Resources\RelationManager;

class LabelsRelationManager extends RelationManager
{
    protected static string $relationship = 'labels';

    protected static ?string $relatedResource = NoteResource::class;

    public static function headerActions(Model $owner): array
    {
        return [...parent::headerActions($owner), AttachAction::for(static::class, $owner)];
    }

    public static function recordActions(Model $owner): array
    {
        return [...parent::recordActions($owner), DetachAction::for(static::class, $owner)];
    }
}
