<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Types;

/**
 * A Mollie payment as the core sees it. The raw resource is kept opaque, for public hooks only.
 *
 * expressRef is metadata.express_ref, which a payment created from an express session inherits;
 * the addresses are what the wallet collected.
 */
final class PaymentSnapshot
{
    private string $id;

    private string $status;

    private ?string $method;

    private Money $amount;

    private ?object $raw;

    public function __construct(
        string $id,
        string $status,
        ?string $method,
        Money $amount,
        ?object $raw = null,
        private string $mode = 'live',
        private ?string $expressRef = null,
        private ?MollieAddress $billingAddress = null,
        private ?MollieAddress $shippingAddress = null
    ) {
        $this->id = $id;
        $this->status = $status;
        $this->method = $method;
        $this->amount = $amount;
        $this->raw = $raw;
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

    public function amount(): Money
    {
        return $this->amount;
    }

    public function raw(): ?object
    {
        return $this->raw;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function expressRef(): ?string
    {
        return $this->expressRef;
    }

    public function billingAddress(): ?MollieAddress
    {
        return $this->billingAddress;
    }

    public function shippingAddress(): ?MollieAddress
    {
        return $this->shippingAddress;
    }
}
