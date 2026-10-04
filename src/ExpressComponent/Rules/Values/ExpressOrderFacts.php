<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Rules\Values;

use Mollie\WooCommerce\Shared\Values\Money;
final class ExpressOrderFacts
{
    /**
     * @param ?string $trackedPaymentId The payment the order already follows.
     */
    public function __construct(private int $orderId, private string $expressRef, private string $createdVia, private ?Money $total, private ?string $trackedPaymentId, private bool $needsPayment, private bool $holdsShipping, private bool $needsShipping)
    {
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
}
