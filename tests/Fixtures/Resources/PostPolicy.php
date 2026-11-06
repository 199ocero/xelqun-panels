<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

use Xelqun\Panels\Tests\Fixtures\Post;
use Xelqun\Panels\Tests\Fixtures\User;

class PostPolicy
{
    /** @var array<int, string> */
    public static array $denied = [];

    /** @var array<int, string> */
    public static array $hiddenTitles = [];

    public function viewAny(User $user): bool
    {
        return ! in_array('viewAny', self::$denied, true);
    }

    public function view(User $user, Post $post): bool
    {
        return ! in_array('view', self::$denied, true) && ! in_array($post->title, self::$hiddenTitles, true);
    }

    public function create(User $user): bool
    {
        return ! in_array('create', self::$denied, true);
    }

    public function update(User $user, Post $post): bool
    {
        return ! in_array('update', self::$denied, true);
    }

    public function delete(User $user, Post $post): bool
    {
        return ! in_array('delete', self::$denied, true);
    }
}
