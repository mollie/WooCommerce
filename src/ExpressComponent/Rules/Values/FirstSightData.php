<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules\Values;

final class FirstSightData
{
    /**
     * @param string|null $gatewayId Null keeps the provisional gateway.
     * @param string|null $unmatchedMethod Mollie method no gateway matches; noted on the order.
     * @param array<string, string> $billing
     * @param array<string, string>|null $shipping Null when not written.
     */
    public function __construct(
        private string $paymentId,
        private string $mode,
        private ?string $gatewayId,
        private ?string $unmatchedMethod,
        private array $billing,
        private ?array $shipping
    ) {
    }

    public function paymentId(): string
    {
        return $this->paymentId;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function gatewayId(): ?string
    {
        return $this->gatewayId;
    }

    public function unmatchedMethod(): ?string
    {
        return $this->unmatchedMethod;
    }

    /**
     * @return array<string, string>
     */
    public function billing(): array
    {
        return $this->billing;
    }

    /**
     * @return array<string, string>|null
     */
    public function shipping(): ?array
    {
        return $this->shipping;
    }
}
