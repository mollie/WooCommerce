<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules\Values;

use Mollie\WooCommerce\Shared\Values\Money;

/**
 * All packages together, cost VAT included.
 */
final class CartShipping
{
    /**
     * @param list<string> $rateIds
     */
    public function __construct(
        private array $rateIds,
        private string $label,
        private Money $cost,
        private string $vatRate
    ) {
    }

    /**
     * @return list<string>
     */
    public function rateIds(): array
    {
        return $this->rateIds;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function cost(): Money
    {
        return $this->cost;
    }

    // Two decimals, as Mollie writes it: '21.00'.
    public function vatRate(): string
    {
        return $this->vatRate;
    }
}
