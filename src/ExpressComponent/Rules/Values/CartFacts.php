<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules\Values;

use Mollie\WooCommerce\Shared\Values\Money;

/**
 * Pricing fields are optional: availability needs only the shipping state.
 */
final class CartFacts
{
    /**
     * @param list<CartLine> $lines
     * @param Money|null $total Including VAT, shipping and fees.
     * @param list<CartFee> $fees
     * @param list<CartCoupon> $coupons
     * @param string $destination The one the shipping rates were calculated for.
     */
    public function __construct(
        private array $lines,
        private bool $needsShipping,
        private bool $shippingDestinationComplete,
        private bool $shippingRateChosen,
        private ?Money $total = null,
        private array $fees = [],
        private array $coupons = [],
        private ?CartShipping $shipping = null,
        private string $cartHash = '',
        private string $destination = ''
    ) {
    }

    /**
     * @return list<CartLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function needsShipping(): bool
    {
        return $this->needsShipping;
    }

    public function shippingDestinationComplete(): bool
    {
        return $this->shippingDestinationComplete;
    }

    public function shippingRateChosen(): bool
    {
        return $this->shippingRateChosen;
    }

    public function total(): ?Money
    {
        return $this->total;
    }

    /**
     * @return list<CartFee>
     */
    public function fees(): array
    {
        return $this->fees;
    }

    /**
     * @return list<CartCoupon>
     */
    public function coupons(): array
    {
        return $this->coupons;
    }

    public function shipping(): ?CartShipping
    {
        return $this->shipping;
    }

    public function cartHash(): string
    {
        return $this->cartHash;
    }

    public function destination(): string
    {
        return $this->destination;
    }
}
