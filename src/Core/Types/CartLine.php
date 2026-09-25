<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Types;

/**
 * One line of the cart, reduced to what the express decisions need.
 */
final class CartLine
{
    public function __construct(private int $productId, private int $quantity, private bool $isSubscription)
    {
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
}
