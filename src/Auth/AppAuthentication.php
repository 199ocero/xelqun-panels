<?php

namespace Xelqun\Panels\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Xelqun\Panels\Contracts\HasAppAuthentication;

/**
 * Checks what someone types at the second step of signing in.
 *
 * @internal
 */
final class AppAuthentication
{
    private const int RECOVERY_CODES = 8;

    /** Longer than the steps a code stays valid for. */
    private const int USED_CODE_SECONDS = 150;

    public static function verify(HasAppAuthentication $user, string $code): bool
    {
        $secret = $user->getAppAuthenticationSecret();
        $code = trim($code);

        if ($secret === null) {
            return false;
        }

        if (ctype_digit($code)) {
            return self::verifyTotp($user, $secret, $code);
        }

        return $user->consumeRecoveryCode(self::hashRecoveryCode($code));
    }

    /**
     * RFC 6238 says a code works once, so one read over a shoulder can't sign in again.
     */
    public static function verifyTotp(HasAppAuthentication $user, string $secret, string $code): bool
    {
        $step = Totp::matchingStep($secret, $code);

        return $step !== null && Cache::add("xelqun-panels:totp:{$user->getAuthIdentifier()}:{$step}", true, self::USED_CODE_SECONDS);
    }

    /**
     * @return array<int, string> Shown once. Only their hashes are kept.
     */
    public static function generateRecoveryCodes(): array
    {
        return array_map(
            fn (): string => Str::lower(Str::random(5).'-'.Str::random(5)),
            range(1, self::RECOVERY_CODES),
        );
    }

    public static function hashRecoveryCode(string $code): string
    {
        return hash('sha256', Str::lower(trim($code)));
    }
}
