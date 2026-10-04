<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Shared\Values;

/**
 * expressRef is metadata.express_ref, inherited from the express session.
 */
final class PaymentSnapshot
{
    public function __construct(private string $id, private string $status, private ?string $method, private \Mollie\WooCommerce\Shared\Values\Money $amount, private string $mode = 'live', private ?string $expressRef = null, private ?\Mollie\WooCommerce\Shared\Values\MollieAddress $billingAddress = null, private ?\Mollie\WooCommerce\Shared\Values\MollieAddress $shippingAddress = null)
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
    public function amount(): \Mollie\WooCommerce\Shared\Values\Money
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
    public function billingAddress(): ?\Mollie\WooCommerce\Shared\Values\MollieAddress
    {
        return $this->billingAddress;
    }
    public function shippingAddress(): ?\Mollie\WooCommerce\Shared\Values\MollieAddress
    {
        return $this->shippingAddress;
    }
}
