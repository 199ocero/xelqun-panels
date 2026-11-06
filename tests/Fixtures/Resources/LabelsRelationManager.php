<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

use Xelqun\Panels\Resources\RelationManager;

class LabelsRelationManager extends RelationManager
{
    protected static string $relationship = 'labels';

    protected static ?string $relatedResource = NoteResource::class;
}
