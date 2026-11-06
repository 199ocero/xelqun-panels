<?php

namespace Xelqun\Panels\Auth\Concerns;

/**
 * Keeps a user's authenticator secret, encrypted, and their recovery codes,
 * hashed. Publish the columns with `php artisan vendor:publish --tag=xelqun-panels-mfa`.
 *
 * @api
 */
trait InteractsWithAppAuthentication
{
    public function initializeInteractsWithAppAuthentication(): void
    {
        $this->mergeCasts([
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'array',
        ]);

        $this->makeHidden(['app_authentication_secret', 'app_authentication_recovery_codes']);
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->getAttribute('app_authentication_secret');
    }

    /**
     * @param  array<int, string>  $recoveryCodeHashes
     */
    public function saveAppAuthentication(?string $secret, array $recoveryCodeHashes = []): void
    {
        $this->forceFill([
            'app_authentication_secret' => $secret,
            'app_authentication_recovery_codes' => $secret === null ? null : $recoveryCodeHashes,
        ])->save();
    }

    public function consumeRecoveryCode(string $hash): bool
    {
        return $this->getConnection()->transaction(function () use ($hash): bool {
            $locked = $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            $codes = $locked->getAttribute('app_authentication_recovery_codes') ?? [];

            if (! in_array($hash, $codes, true)) {
                return false;
            }

            $this->forceFill(['app_authentication_recovery_codes' => array_values(array_diff($codes, [$hash]))])->save();

            return true;
        });
    }
}
