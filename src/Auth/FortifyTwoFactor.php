<?php

namespace Xelqun\Panels\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Fortify's own two-factor, as a starter kit sets it up. The panel signs in on
 * the app's guard, so without this its login would start a session that
 * Fortify's login only gives after a code.
 *
 * @internal
 */
final class FortifyTwoFactor
{
    /**
     * Whether the app's Fortify login would ask this user for a code.
     */
    public static function isEnabledFor(?Authenticatable $user): bool
    {
        return $user !== null
            && class_exists(Features::class)
            && Features::enabled(Features::twoFactorAuthentication())
            && in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user), true)
            && $user->hasEnabledTwoFactorAuthentication();
    }

    /**
     * A code from the authenticator app, or one of Fortify's recovery codes,
     * which is then replaced, as Fortify's own challenge does.
     */
    public static function verify(Authenticatable $user, string $code): bool
    {
        if (! self::isEnabledFor($user)) {
            return false;
        }

        $code = trim($code);

        if (ctype_digit($code)) {
            return app(TwoFactorAuthenticationProvider::class)->verify(
                Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
                $code,
            );
        }

        /** @var array<int, string> $recoveryCodes */
        $recoveryCodes = $user->recoveryCodes();
        $recoveryCode = array_find($recoveryCodes, fn (string $recoveryCode): bool => hash_equals($recoveryCode, $code));

        if ($recoveryCode === null) {
            return false;
        }

        $user->replaceRecoveryCode($recoveryCode);

        return true;
    }
}
