<?php

namespace Xelqun\Panels\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Lets a user model sign in with an authenticator app. The
 * InteractsWithAppAuthentication trait is all of this.
 *
 * @api
 */
interface HasAppAuthentication extends Authenticatable
{
    public function getAppAuthenticationSecret(): ?string;

    /**
     * Null turns it off.
     *
     * @param  array<int, string>  $recoveryCodeHashes
     */
    public function saveAppAuthentication(?string $secret, array $recoveryCodeHashes = []): void;

    /**
     * Use up a recovery code. Saying whether it worked and spending it must be one
     * step, so the same code can't be spent twice at once.
     */
    public function consumeRecoveryCode(string $hash): bool;
}
