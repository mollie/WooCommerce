<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Rules\Values;

use Mollie\WooCommerce\Shared\Values\Money;
/**
 * Amount is what it takes off, VAT included, as a positive value.
 */
final class CartCoupon
{
    public function __construct(private string $code, private Money $amount)
    {
    }
    public function code(): string
    {
        return $this->code;
    }
    public function amount(): Money
    {
        return $this->amount;
    }
}
