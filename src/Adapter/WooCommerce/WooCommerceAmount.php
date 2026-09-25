<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Adapter\WooCommerce;

use InvalidArgumentException;
use Mollie\WooCommerce\Core\Types\Money;

/**
 * The one place a WooCommerce amount, a float or a numeric string, becomes Money.
 *
 * WooCommerce keeps amounts as floats rounded to the store's decimals. They are written with two
 * decimals, or with none for a currency Money knows has no minor unit, and never compared as floats.
 */
final class WooCommerceAmount
{
    /**
     * @param float|int|string $amount
     *
     * @throws InvalidArgumentException When the currency is not an ISO 4217 code.
     */
    public static function toMoney($amount, string $currency): Money
    {
        $amount = (float) $amount;
        try {
            return Money::fromDecimal(number_format($amount, 2, '.', ''), $currency);
        } catch (InvalidArgumentException $exception) {
            // A currency without decimals.
            return Money::fromDecimal(number_format($amount, 0, '.', ''), $currency);
        }
    }
}
