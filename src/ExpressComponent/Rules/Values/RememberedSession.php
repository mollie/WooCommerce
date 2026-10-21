<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules\Values;

final class RememberedSession
{
    /**
     * @param int $expiresAt Unix time.
     */
    public function __construct(
        private string $sessionId,
        private string $clientAccessToken,
        private string $expressRef,
        private string $fingerprint,
        private int $expiresAt
    ) {
    }

    public function sessionId(): string
    {
        return $this->sessionId;
    }

    public function clientAccessToken(): string
    {
        return $this->clientAccessToken;
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
