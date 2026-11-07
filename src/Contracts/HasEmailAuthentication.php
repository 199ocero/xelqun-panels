<?php

namespace Xelqun\Panels\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Lets a user model get a sign in code by email. The
 * InteractsWithAppAuthentication trait is all of this.
 *
 * @api
 */
interface HasEmailAuthentication extends Authenticatable
{
    public function hasEmailAuthentication(): bool;

    public function saveEmailAuthentication(bool $isOn): void;
}
