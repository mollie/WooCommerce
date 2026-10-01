<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Types;

/**
 * The cart and the checkout form's shipping state, as plain values.
 *
 * The pricing fields are optional so availability, which needs none of them, can be asked with the
 * shipping state alone. The destination is only ever an input of the pricing fingerprint.
 */
final class CartFacts
{
    /**
     * @param list<CartLine> $lines
     * @param Money|null $total The cart total WooCommerce calculated, including VAT, shipping and fees.
     * @param list<CartFee> $fees
     * @param list<CartCoupon> $coupons
     * @param string $cartHash WooCommerce's hash of the cart contents.
     * @param string $destination The shipping destination the rates were calculated for.
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
