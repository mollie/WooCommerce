<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules;

/**
 * A session's amount is fixed, and a token must not die while the shopper is in the wallet.
 * A session whose order is paid or cancelled is spent: its token would only lead back to that order.
 * A session belongs to the mode and profile that created it; after a switch its token is another account's.
 */
final class SessionReuse
{
    public static function fits(
        string $sessionFingerprint,
        string $sessionAccount,
        int $sessionExpiresAt,
        string $fingerprint,
        string $account,
        int $now,
        int $marginSeconds,
        bool $orderSettled
    ): bool {

        return !$orderSettled
            && $sessionFingerprint === $fingerprint
            && $sessionAccount === $account
            && $sessionExpiresAt - $now > $marginSeconds;
    }
}
