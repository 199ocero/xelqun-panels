<?php

namespace Xelqun\Panels\Tests\Fixtures\Resources;

use Illuminate\Database\Eloquent\Model;
use Xelqun\Panels\Tests\Fixtures\User;

/**
 * Everything is allowed but what's in $denied, for comments, labels and notes.
 */
class RelatedPolicy
{
    /** @var array<int, string> */
    public static array $denied = [];

    public function viewAny(User $user): bool
    {
        return ! in_array('viewAny', self::$denied, true);
    }

    public function view(User $user, Model $record): bool
    {
        return ! in_array('view', self::$denied, true);
    }

    public function create(User $user): bool
    {
        return ! in_array('create', self::$denied, true);
    }

    public function update(User $user, Model $record): bool
    {
        return ! in_array('update', self::$denied, true);
    }

    public function delete(User $user, Model $record): bool
    {
        return ! in_array('delete', self::$denied, true);
    }

    public function attach(User $user): bool
    {
        return ! in_array('attach', self::$denied, true);
    }

    public function detach(User $user, Model $record): bool
    {
        return ! in_array('detach', self::$denied, true);
    }

    public function associate(User $user): bool
    {
        return ! in_array('associate', self::$denied, true);
    }

    public function dissociate(User $user, Model $record): bool
    {
        return ! in_array('dissociate', self::$denied, true);
    }
}
