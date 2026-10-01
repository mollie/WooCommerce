<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Types;

/**
 * The cart and the checkout form's shipping state, as plain values.
 */
final class CartFacts
{
    /**
     * @param list<CartLine> $lines
     */
    public function __construct(
        private array $lines,
        private bool $needsShipping,
        private bool $shippingDestinationComplete,
        private bool $shippingRateChosen
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
}
