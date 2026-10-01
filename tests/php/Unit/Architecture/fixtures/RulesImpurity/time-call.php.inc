<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules;

/**
 * Breaks one rule: a rule asks the clock. Take a Clock reading as an argument instead.
 */
final class AsksTheClock
{
    public static function decide(int $expiresAt): bool
    {
        return $expiresAt > time();
    }
}
