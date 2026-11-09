<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

use Xelqun\Panels\Resources\Pages\ManageRelatedRecords;

class ManagePostComments extends ManageRelatedRecords
{
    protected static string $relationManager = CommentsRelationManager::class;
}
