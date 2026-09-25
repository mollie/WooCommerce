<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Types;

/**
 * A coupon applied to the cart: what it takes off, including VAT, as a positive amount.
 */
final class CartCoupon
{
    public function __construct(private string $code, private \Mollie\WooCommerce\Core\Types\Money $amount)
    {
    }
    public function code(): string
    {
        return $this->code;
    }
    public function amount(): \Mollie\WooCommerce\Core\Types\Money
    {
        return $this->amount;
    }
}
