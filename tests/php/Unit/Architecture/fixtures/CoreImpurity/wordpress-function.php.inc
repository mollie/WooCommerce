<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

/**
 * Breaks one rule: a pure decision reaches for WordPress instead of taking a ShopConfig value.
 */
final class ReadsAnOption
{
    public static function decide(string $gatewayId): bool
    {
        return get_option($gatewayId . '_enabled') === 'yes';
    }
}
