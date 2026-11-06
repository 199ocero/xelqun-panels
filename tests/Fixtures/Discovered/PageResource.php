<?php

namespace Xelqun\Panels\Tests\Fixtures\Discovered;

use Xelqun\Panels\Resources\Resource;
use Xelqun\Panels\Tests\Fixtures\Post;

class PageResource extends Resource
{
    protected static string $model = Post::class;

    protected static ?string $slug = 'discovered-pages';

    public static function form(): array
    {
        return [];
    }

    public static function columns(): array
    {
        return [];
    }
}
