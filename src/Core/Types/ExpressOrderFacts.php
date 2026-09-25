<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Types;

/**
 * What the store knows about an express order: the shopper's remembered session and the order that
 * already carries its express_ref, if any. All null when no session was remembered.
 *
 * When the webhook resolves a payment the facts describe the order that carries the payment's ref:
 * how it was created, its total, the payment it already tracks, and which addresses it holds.
 */
final class ExpressOrderFacts
{
    public function __construct(private ?string $sessionId = null, private ?string $expressRef = null, private ?string $fingerprint = null, private ?int $expiresAt = null, private ?int $existingOrderId = null, private ?string $createdVia = null, private ?\Mollie\WooCommerce\Core\Types\Money $total = null, private ?string $trackedPaymentId = null, private bool $needsPayment = \false, private bool $holdsBilling = \false, private bool $holdsShipping = \false, private bool $needsShipping = \false)
    {
    }
    public function hasSession(): bool
    {
        return $this->sessionId !== null && $this->expressRef !== null;
    }
    public function sessionId(): ?string
    {
        return $this->sessionId;
    }
    public function expressRef(): ?string
    {
        return $this->expressRef;
    }
    public function fingerprint(): ?string
    {
        return $this->fingerprint;
    }
    public function expiresAt(): ?int
    {
        return $this->expiresAt;
    }
    public function existingOrderId(): ?int
    {
        return $this->existingOrderId;
    }
    public function createdVia(): ?string
    {
        return $this->createdVia;
    }
    public function total(): ?\Mollie\WooCommerce\Core\Types\Money
    {
        return $this->total;
    }
    /**
     * The Mollie payment id the order is already linked to, or null.
     */
    public function trackedPaymentId(): ?string
    {
        return $this->trackedPaymentId;
    }
    public function needsPayment(): bool
    {
        return $this->needsPayment;
    }
    public function holdsBilling(): bool
    {
        return $this->holdsBilling;
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
