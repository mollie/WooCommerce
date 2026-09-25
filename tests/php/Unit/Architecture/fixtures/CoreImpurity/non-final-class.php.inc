<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

/**
 * Breaks one rule: a core class that can be extended is a core class that can be made impure.
 */
class CanBeExtended
{
    public static function decide(string $reason): bool
    {
        return $reason === 'cart_changed';
    }
}
