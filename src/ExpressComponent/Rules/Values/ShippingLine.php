<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules\Values;

use Mollie\WooCommerce\Shared\Values\Money;

final class ShippingLine
{
    public function __construct(
        private string $rateId,
        private string $label,
        private Money $gross,
        private Money $net
    ) {
    }

    public function rateId(): string
    {
        return $this->rateId;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function gross(): Money
    {
        return $this->gross;
    }

    public function net(): Money
    {
        return $this->net;
    }

    public function tax(): Money
    {
        return Money::fromMinorUnits($this->gross->minorUnits() - $this->net->minorUnits(), $this->gross->currency());
    }
}
