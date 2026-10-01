<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

/**
 * Breaks one rule: the core asks the clock. Take a Clock reading as an argument instead.
 */
final class AsksTheClock
{
    public static function decide(int $expiresAt): bool
    {
        return $expiresAt > time();
    }
}
