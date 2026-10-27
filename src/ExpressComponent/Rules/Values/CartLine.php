<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules\Values;

use Mollie\WooCommerce\Shared\Values\Money;

final class CartLine
{
    /**
     * @param Money|null $subtotal Including VAT, before coupons.
     * @param string $vatRate Two decimals, as Mollie writes it: '21.00'.
     */
    public function __construct(
        private int $productId,
        private int $quantity,
        private bool $isSubscription,
        private string $name = '',
        private ?Money $subtotal = null,
        private string $vatRate = '0.00'
    ) {
    }

    public function productId(): int
    {
        return $this->productId;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function isSubscription(): bool
    {
        return $this->isSubscription;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function subtotal(): ?Money
    {
        return $this->subtotal;
    }

    public function vatRate(): string
    {
        return $this->vatRate;
    }
}
