<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Panels\Navigation\NavigationItem;
use Xelqun\Panels\Resources\Resource;
use Xelqun\Panels\Tests\Fixtures\Comment;
use Xelqun\Tables\Columns\TextColumn;

/**
 * Comments live under a post, as /posts/1/post-comments.
 */
class PostCommentResource extends Resource
{
    protected static string $model = Comment::class;

    protected static ?string $slug = 'post-comments';

    protected static ?string $recordTitleAttribute = 'body';

    protected static ?string $parentResource = PostResource::class;

    protected static ?string $parentRelationship = 'comments';

    public static function form(): array
    {
        return [TextInput::make('body')->required()];
    }

    public static function columns(): array
    {
        return [TextColumn::make('body')];
    }

    public static function getRecordSubNavigation(Model $record): array
    {
        return [NavigationItem::make('Details')->url(fn (): string => static::getUrl('view', ['record' => $record]))];
    }
}
