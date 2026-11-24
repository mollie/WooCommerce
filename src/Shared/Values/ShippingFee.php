<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Shared\Values;

/**
 * The shipping_fee line of a payment: the option the shopper chose in the wallet.
 * The reference is the store's own rate id, which the store gave Mollie as the option's reference.
 */
final class ShippingFee
{
    public function __construct(private string $reference, private string $label, private \Mollie\WooCommerce\Shared\Values\Money $amount)
    {
    }
    public function reference(): string
    {
        return $this->reference;
    }
    public function label(): string
    {
        return $this->label;
    }
    public function amount(): \Mollie\WooCommerce\Shared\Values\Money
    {
        return $this->amount;
    }
}
