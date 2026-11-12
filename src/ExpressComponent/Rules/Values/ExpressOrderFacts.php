<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules\Values;

use Mollie\WooCommerce\Shared\Values\Money;

final class ExpressOrderFacts
{
    /**
     * @param ?string $trackedPaymentId The payment the order already follows.
     * @param list<string> $processed
     * @param bool $webhookNeedsPayment WebhookGuards::needsPayment().
     * @param bool $holdsShippingLine The order already has a shipping line.
     * @param list<ShippingTaxRate> $shippingTaxRates The rates WooCommerce taxes this order's shipping with, in its order; read only for a payment.
     */
    public function __construct(
        private int $orderId,
        private string $expressRef,
        private string $createdVia,
        private ?Money $total,
        private ?string $trackedPaymentId,
        private bool $needsPayment,
        private bool $holdsShipping,
        private bool $needsShipping,
        private ?string $cancelledBy = null,
        private array $processed = [],
        private bool $webhookNeedsPayment = false,
        private bool $cancelled = false,
        private bool $holdsShippingLine = false,
        private array $shippingTaxRates = []
    ) {
    }

    public function orderId(): int
    {
        return $this->orderId;
    }

    public function expressRef(): string
    {
        return $this->expressRef;
    }

    public function createdVia(): string
    {
        return $this->createdVia;
    }

    public function total(): ?Money
    {
        return $this->total;
    }

    public function trackedPaymentId(): ?string
    {
        return $this->trackedPaymentId;
    }

    public function needsPayment(): bool
    {
        return $this->needsPayment;
    }

    public function holdsShipping(): bool
    {
        return $this->holdsShipping;
    }

    public function needsShipping(): bool
    {
        return $this->needsShipping;
    }

    public function cancelledBy(): ?string
    {
        return $this->cancelledBy;
    }

    /**
     * @return list<string>
     */
    public function processed(): array
    {
        return $this->processed;
    }

    public function webhookNeedsPayment(): bool
    {
        return $this->webhookNeedsPayment;
    }

    public function cancelled(): bool
    {
        return $this->cancelled;
    }

    public function holdsShippingLine(): bool
    {
        return $this->holdsShippingLine;
    }

    /**
     * @return list<ShippingTaxRate> Empty when the order's shipping carries no tax.
     */
    public function shippingTaxRates(): array
    {
        return $this->shippingTaxRates;
    }
}
