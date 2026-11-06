<?php

namespace Xelqun\Panels\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A model with no policy.
 */
class Unguarded extends Model
{
    protected $table = 'posts';

    protected $guarded = [];
}
