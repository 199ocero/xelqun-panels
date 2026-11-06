<?php

namespace Xelqun\Panels\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Xelqun\Panels\Contracts\PanelUser;
use Xelqun\Panels\Panel;

class User extends Authenticatable implements PanelUser
{
    public static bool $allowed = true;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    public function canAccessPanel(Panel $panel): bool
    {
        return self::$allowed;
    }
}
