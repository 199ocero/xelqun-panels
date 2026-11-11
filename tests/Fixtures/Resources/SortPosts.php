<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

use Xelqun\Panels\Resources\Pages\ResourcePage;

class SortPosts extends ResourcePage
{
    protected static ?string $routePath = 'sort';

    public function getHeading(): string
    {
        return 'Sort posts';
    }

    public function content(): array
    {
        return [];
    }
}
