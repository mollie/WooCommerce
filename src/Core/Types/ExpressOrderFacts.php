<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Types;

/**
 * A snapshot of one order that carries an express_ref: what a repeated submit, a webhook match and
 * the first-sight writes need to know about it.
 */
final class ExpressOrderFacts
{
    /**
     * @param ?string $trackedPaymentId The payment the order already follows, if any.
     */
    public function __construct(private int $orderId, private string $expressRef, private string $createdVia, private ?\Mollie\WooCommerce\Core\Types\Money $total, private ?string $trackedPaymentId, private bool $needsPayment, private bool $holdsShipping, private bool $needsShipping)
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
    public function total(): ?\Mollie\WooCommerce\Core\Types\Money
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
