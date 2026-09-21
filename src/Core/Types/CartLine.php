<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Types;

/**
 * One line of the cart, reduced to what the express decisions need.
 *
 * The pricing fields are optional so availability, which needs none of them, can be asked with
 * a line alone.
 */
final class CartLine
{
    /**
     * @param Money|null $subtotal The line total including VAT, before coupons.
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
