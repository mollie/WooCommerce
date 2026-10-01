<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules;

/**
 * Breaks one rule: a decision that cannot be replayed from its inputs is not a decision.
 */
final class RollsADie
{
    public static function decide(): int
    {
        return rand(1, 6);
    }
}
