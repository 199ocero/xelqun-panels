<?php

namespace Xelqun\Panels\Tests\Fixtures\Widgets;

use Illuminate\Contracts\Auth\Authenticatable;

class DeniedList extends PendingPosts
{
    public function authorize(?Authenticatable $user): bool
    {
        return false;
    }
}
