<?php

namespace Xelqun\Panels\Tests\Fixtures;

use Laravel\Fortify\TwoFactorAuthenticatable;

/** A user as a starter kit makes it, with Fortify's own two-factor. */
class FortifyUser extends User
{
    use TwoFactorAuthenticatable;

    protected $table = 'users';
}
