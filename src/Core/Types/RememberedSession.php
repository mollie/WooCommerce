<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Types;

/**
 * The Checkout Session the store remembers for one shopper: what it was priced for and until when.
 * The client access token is not part of it; only the session route ever needs that.
 */
final class RememberedSession
{
    /**
     * @param int $expiresAt Unix time.
     */
    public function __construct(private string $sessionId, private string $expressRef, private string $fingerprint, private int $expiresAt)
    {
    }
    public function sessionId(): string
    {
        return $this->sessionId;
    }
    public function expressRef(): string
    {
        return $this->expressRef;
    }
    public function fingerprint(): string
    {
        return $this->fingerprint;
    }
    public function expiresAt(): int
    {
        return $this->expiresAt;
    }
}
