<?php

namespace Xelqun\Panels\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $guarded = [];

    protected $casts = ['tags' => 'array'];
}
