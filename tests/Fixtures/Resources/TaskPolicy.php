<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

use Xelqun\Panels\Tests\Fixtures\Task;
use Xelqun\Panels\Tests\Fixtures\User;

class TaskPolicy extends RelatedPolicy
{
    public function restore(User $user, Task $task): bool
    {
        return ! in_array('restore', self::$denied, true);
    }

    public function forceDelete(User $user, Task $task): bool
    {
        return ! in_array('forceDelete', self::$denied, true);
    }

    public function replicate(User $user, Task $task): bool
    {
        return ! in_array('replicate', self::$denied, true);
    }
}
