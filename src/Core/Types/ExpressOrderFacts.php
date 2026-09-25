<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Types;

/**
 * What the store knows about an express order request: the shopper's remembered session and the
 * order that already carries its express_ref, if any. All null when no session was remembered.
 */
final class ExpressOrderFacts
{
    public function __construct(private ?string $sessionId = null, private ?string $expressRef = null, private ?string $fingerprint = null, private ?int $expiresAt = null, private ?int $existingOrderId = null)
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
}
