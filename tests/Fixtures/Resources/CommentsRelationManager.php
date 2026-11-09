<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

use Illuminate\Database\Eloquent\Model;
use Xelqun\Forms\Components\TextInput;
use Xelqun\Panels\Actions\AssociateAction;
use Xelqun\Panels\Actions\DissociateAction;
use Xelqun\Panels\Resources\RelationManager;
use Xelqun\Tables\Columns\TextColumn;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static string $recordTitleAttribute = 'body';

    public static bool $tuned = false;

    public static function getBadge(Model $owner): string|int|null
    {
        return static::$tuned ? $owner->comments()->count() : null;
    }

    public static function getBadgeColor(Model $owner): ?string
    {
        return 'info';
    }

    public static function isReadOnly(): ?bool
    {
        return static::$tuned ? false : null;
    }

    public static function isLazy(): bool
    {
        return static::$tuned;
    }

    public static function headerActions(Model $owner): array
    {
        return [...parent::headerActions($owner), AssociateAction::for(static::class, $owner)];
    }

    public static function recordActions(Model $owner): array
    {
        return [...parent::recordActions($owner), DissociateAction::for(static::class, $owner)];
    }

    public static function form(): array
    {
        return [TextInput::make('body')->required()->maxLength(20)];
    }

    public static function columns(): array
    {
        return [TextColumn::make('body')->searchable()];
    }
}
