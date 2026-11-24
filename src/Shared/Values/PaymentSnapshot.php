<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Shared\Values;

/**
 * expressRef is metadata.express_ref, inherited from the express session.
 * wallet is details.wallet: Mollie reports Apple Pay and Google Pay both as creditcard.
 * shippingFee is the payment's shipping_fee line: the option the shopper chose in the wallet.
 */
final class PaymentSnapshot
{
    public function __construct(
        private string $id,
        private string $status,
        private ?string $method,
        private Money $amount,
        private string $mode = 'live',
        private ?string $expressRef = null,
        private ?MollieAddress $billingAddress = null,
        private ?MollieAddress $shippingAddress = null,
        private ?string $wallet = null,
        private ?ShippingFee $shippingFee = null
    ) {
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

    public function wallet(): ?string
    {
        return $this->wallet;
    }

    public function shippingFee(): ?ShippingFee
    {
        return $this->shippingFee;
    }
}
