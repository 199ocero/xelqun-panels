<?php

namespace Xelqun\Panels\Tests\Fixtures\Widgets;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Xelqun\Actions\Action;
use Xelqun\Panels\Tests\Fixtures\Post;
use Xelqun\Panels\Widgets\ListWidget;

class PendingPosts extends ListWidget
{
    protected static int $sort = 1;

    protected static int $columnSpan = 8;

    protected static int $limit = 2;

    protected static ?string $heading = 'Needs your approval';

    public static bool $canApprove = true;

    protected function getSeeAllUrl(): string
    {
        return '/posts';
    }

    public function query(): Builder
    {
        return Post::query()->where('status', 'draft')->where('team', 'red');
    }

    protected function title(Model $record): string
    {
        return $record->title;
    }

    protected function meta(Model $record): string
    {
        return "Team {$record->team}";
    }

    protected function icon(Model $record): string
    {
        return 'file-text';
    }

    protected function iconColor(Model $record): string
    {
        return 'primary';
    }

    protected function progress(Model $record): float
    {
        return 0.5;
    }

    protected function progressNote(Model $record): string
    {
        return 'Halfway';
    }

    public function recordActions(): array
    {
        return [
            Action::make('approve')
                ->authorize(fn (): bool => self::$canApprove)
                ->action(fn (Post $record) => $record->update(['status' => 'live'])),
        ];
    }
}
