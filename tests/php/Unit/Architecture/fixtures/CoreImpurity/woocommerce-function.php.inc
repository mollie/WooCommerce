<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

/**
 * Breaks one rule: a pure decision reaches for WooCommerce instead of taking OrderFacts.
 */
final class ReadsAnOrder
{
    public static function decide(int $orderId): string
    {
        return (string) wc_get_order($orderId)->get_status();
    }
}
