<?php

namespace Xelqun\Panels\Auth;

/**
 * Time-based one-time passwords, as RFC 6238 defines them. It's the 30 second,
 * 6 digit, SHA-1 kind that every authenticator app reads.
 *
 * @internal
 */
final class Totp
{
    private const int PERIOD = 30;

    /** A clock a step fast or slow still works. */
    private const int DRIFT = 1;

    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(): string
    {
        $bits = '';

        foreach (str_split(random_bytes(20)) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        return implode('', array_map(fn (string $chunk): string => self::ALPHABET[bindec(str_pad($chunk, 5, '0'))], str_split($bits, 5)));
    }

    /**
     * Which time step a code belongs to, or null if it matches none near now.
     * Every step is checked, so timing doesn't show how close a guess came.
     */
    public static function matchingStep(string $secret, string $code): ?int
    {
        $currentStep = (int) floor(now()->getTimestamp() / self::PERIOD);
        $match = null;

        foreach (range($currentStep - self::DRIFT, $currentStep + self::DRIFT) as $step) {
            if (hash_equals(self::codeAt($secret, $step), $code)) {
                $match = $step;
            }
        }

        return $match;
    }

    public static function codeAt(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), self::decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * What an authenticator app reads, from a link or a typed key.
     */
    public static function uri(string $secret, string $issuer, string $account): string
    {
        $label = rawurlencode($issuer).':'.rawurlencode($account);

        return "otpauth://totp/{$label}?".http_build_query(['secret' => $secret, 'issuer' => $issuer], encoding_type: PHP_QUERY_RFC3986);
    }

    private static function decode(string $secret): string
    {
        $bits = '';

        foreach (str_split(strtoupper($secret)) as $character) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $character)), 5, '0', STR_PAD_LEFT);
        }

        $bytes = array_filter(str_split($bits, 8), fn (string $byte): bool => strlen($byte) === 8);

        return implode('', array_map(fn (string $byte): string => chr((int) bindec($byte)), $bytes));
    }
}
