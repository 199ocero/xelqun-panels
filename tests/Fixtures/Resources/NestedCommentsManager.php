<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

use Xelqun\Panels\Resources\RelationManager;

class NestedCommentsManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $relatedResource = PostCommentResource::class;
}
