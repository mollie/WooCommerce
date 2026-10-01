<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules\Values;

/**
 * What a matched express order is given the first time its payment is seen, as decided by
 * FirstSight. ExpressOrderWriter carries it out.
 */
final class FirstSightData
{
    /**
     * @param string|null $gatewayId The plugin's payment method of the wallet that paid; null keeps the provisional one.
     * @param string|null $unmatchedMethod The Mollie method that paid, when no payment method matches it; noted on the order.
     * @param array<string, string> $billing WooCommerce billing fields from the wallet; empty when it gave none.
     * @param array<string, string>|null $shipping WooCommerce shipping fields from the wallet; null when they are not written.
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
