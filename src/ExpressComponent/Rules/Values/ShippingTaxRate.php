<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules\Values;

/**
 * One tax rate of the order that also taxes shipping.
 */
final class ShippingTaxRate
{
    public function __construct(
        private int $rateId,
        private float $percent,
        private bool $compound
    ) {
    }

    public function rateId(): int
    {
        return $this->rateId;
    }

    /**
     * @return float Percent, e.g. 21.0.
     */
    public function percent(): float
    {
        return $this->percent;
    }

    public function compound(): bool
    {
        return $this->compound;
    }
}
