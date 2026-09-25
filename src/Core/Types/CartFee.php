<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Types;

/**
 * A fee on the cart, including its VAT. A negative fee is a discount.
 */
final class CartFee
{
    public function __construct(private string $name, private \Mollie\WooCommerce\Core\Types\Money $amount, private string $vatRate)
    {
    }
    public function name(): string
    {
        return $this->name;
    }
    public function amount(): \Mollie\WooCommerce\Core\Types\Money
    {
        return $this->amount;
    }
    /**
     * Two decimals, as Mollie writes it: '21.00'.
     */
    public function vatRate(): string
    {
        return $this->vatRate;
    }
}
