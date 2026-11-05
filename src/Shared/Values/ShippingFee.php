<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Shared\Values;

/**
 * The shipping_fee line of a payment: the option the shopper chose in the wallet.
 * The reference is the store's own rate id, which the store gave Mollie as the option's reference.
 */
final class ShippingFee
{
    public function __construct(
        private string $reference,
        private string $label,
        private Money $amount
    ) {
    }

    public function reference(): string
    {
        return $this->reference;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function amount(): Money
    {
        return $this->amount;
    }
}
