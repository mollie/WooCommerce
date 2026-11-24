<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules\Values;

use Mollie\WooCommerce\Shared\Values\Money;

/**
 * A shipping option as the shipping callback offered it to the wallet: its price with tax and the tax
 * WooCommerce charged in it, per tax rate id, rounded to the cent.
 */
final class PricedShippingOption
{
    /**
     * @param array<int, Money> $taxes Tax per tax rate id, in the currency of the gross.
     */
    public function __construct(
        private string $rateId,
        private Money $gross,
        private array $taxes
    ) {
    }

    public function rateId(): string
    {
        return $this->rateId;
    }

    public function gross(): Money
    {
        return $this->gross;
    }

    /**
     * @return array<int, Money>
     */
    public function taxes(): array
    {
        return $this->taxes;
    }
}
