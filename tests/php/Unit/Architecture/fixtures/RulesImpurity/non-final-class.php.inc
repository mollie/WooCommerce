<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules;

/**
 * Breaks one rule: a rule that can be extended is a rule that can be made impure.
 */
class CanBeExtended
{
    public static function decide(string $reason): bool
    {
        return $reason === 'cart_changed';
    }
}
