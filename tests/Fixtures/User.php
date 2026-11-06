<?php

namespace Xelqun\Panels\Tests\Fixtures;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Xelqun\Panels\Auth\Concerns\InteractsWithAppAuthentication;
use Xelqun\Panels\Contracts\HasAppAuthentication;
use Xelqun\Panels\Contracts\PanelUser;
use Xelqun\Panels\Panel;

class User extends Authenticatable implements HasAppAuthentication, MustVerifyEmail, PanelUser
{
    use InteractsWithAppAuthentication;
    use Notifiable;

    public static bool $allowed = true;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['email_verified_at' => 'datetime'];

    public function canAccessPanel(Panel $panel): bool
    {
        return self::$allowed;
    }
}
