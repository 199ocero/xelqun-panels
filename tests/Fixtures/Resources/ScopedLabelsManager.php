<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

use Illuminate\Database\Eloquent\Model;
use Xelqun\Panels\Actions\AttachAction;
use Xelqun\Panels\Resources\RelationManager;

class ScopedLabelsManager extends RelationManager
{
    protected static string $relationship = 'labels';

    protected static ?string $relatedResource = ScopedLabelResource::class;

    public static function headerActions(Model $owner): array
    {
        return [AttachAction::for(static::class, $owner)];
    }
}
