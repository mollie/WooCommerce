<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

use InvalidArgumentException;
use Mollie\WooCommerce\Shared\Values\Money;
/** WooCommerce amounts are floats: format them to a decimal string, never compare as floats. */
final class WooCommerceAmount
{
    /**
     * @param float|int|string $amount
     * @throws InvalidArgumentException When the currency is not an ISO 4217 code.
     */
    public static function toMoney($amount, string $currency): Money
    {
        $amount = (float) $amount;
        try {
            return Money::fromDecimal(number_format($amount, 2, '.', ''), $currency);
        } catch (InvalidArgumentException $exception) {
            // Currency without a minor unit.
            return Money::fromDecimal(number_format($amount, 0, '.', ''), $currency);
        }
    }
}
