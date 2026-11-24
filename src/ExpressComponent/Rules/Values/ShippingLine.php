<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules\Values;

use Mollie\WooCommerce\Shared\Values\Money;

final class ShippingLine
{
    /**
     * @param array<int, Money> $taxes Tax per tax rate id, in the currency of the gross.
     */
    public function __construct(
        private string $rateId,
        private string $label,
        private Money $gross,
        private array $taxes
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
        return Money::fromMinorUnits($this->gross->minorUnits() - $this->tax()->minorUnits(), $this->gross->currency());
    }

    public function tax(): Money
    {
        $minor = 0;
        foreach ($this->taxes as $tax) {
            $minor += $tax->minorUnits();
        }

        return Money::fromMinorUnits($minor, $this->gross->currency());
    }

    /**
     * @return array<int, Money> Tax per tax rate id.
     */
    public function taxes(): array
    {
        return $this->taxes;
    }
}
