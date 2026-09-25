<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Types;

/**
 * A Mollie payment as the core sees it: plain values, no SDK object.
 *
 * expressRef is metadata.express_ref, which a payment created from an express session inherits;
 * the addresses are what the wallet collected.
 */
final class PaymentSnapshot
{
    public function __construct(private string $id, private string $status, private ?string $method, private \Mollie\WooCommerce\Core\Types\Money $amount, private string $mode = 'live', private ?string $expressRef = null, private ?\Mollie\WooCommerce\Core\Types\MollieAddress $billingAddress = null, private ?\Mollie\WooCommerce\Core\Types\MollieAddress $shippingAddress = null)
    {
    }
    public function id(): string
    {
        return $this->id;
    }
    public function status(): string
    {
        return $this->status;
    }
    public function method(): ?string
    {
        return $this->method;
    }
    public function amount(): \Mollie\WooCommerce\Core\Types\Money
    {
        return $this->amount;
    }
    public function mode(): string
    {
        return $this->mode;
    }
    public function expressRef(): ?string
    {
        return $this->expressRef;
    }
    public function billingAddress(): ?\Mollie\WooCommerce\Core\Types\MollieAddress
    {
        return $this->billingAddress;
    }
    public function shippingAddress(): ?\Mollie\WooCommerce\Core\Types\MollieAddress
    {
        return $this->shippingAddress;
    }
}
