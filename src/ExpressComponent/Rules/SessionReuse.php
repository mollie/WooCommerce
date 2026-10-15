<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Rules;

/**
 * A session's amount is fixed, and a token must not die while the shopper is in the wallet.
 */
final class SessionReuse
{
    public static function fits(string $sessionFingerprint, int $sessionExpiresAt, string $fingerprint, int $now, int $marginSeconds): bool
    {
        return $sessionFingerprint === $fingerprint && $sessionExpiresAt - $now > $marginSeconds;
    }
}
