<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Types;

/**
 * The shipping the shopper chose on the checkout form: the rate ids of every package and their
 * cost together, including VAT.
 */
final class CartShipping
{
    /**
     * @param list<string> $rateIds
     */
    public function __construct(private array $rateIds, private string $label, private \Mollie\WooCommerce\Core\Types\Money $cost, private string $vatRate)
    {
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
    public function cost(): \Mollie\WooCommerce\Core\Types\Money
    {
        return $this->cost;
    }
    /**
     * Two decimals, as Mollie writes it: '21.00'.
     */
    public function vatRate(): string
    {
        return $this->vatRate;
    }
}
