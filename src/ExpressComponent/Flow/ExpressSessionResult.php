<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Flow;

final class ExpressSessionResult
{
    private function __construct(
        private ?string $clientAccessToken,
        private ?string $expiresAt,
        private ?string $code,
        private int $httpStatus
    ) {
    }

    public static function started(string $clientAccessToken, string $expiresAt): self
    {
        return new self($clientAccessToken, $expiresAt, null, 200);
    }

    public static function refused(string $code, int $httpStatus): self
    {
        return new self(null, null, $code, $httpStatus);
    }

    public function isStarted(): bool
    {
        return $this->code === null;
    }

    public function clientAccessToken(): string
    {
        return (string) $this->clientAccessToken;
    }

    public function expiresAt(): string
    {
        return (string) $this->expiresAt;
    }

    public function code(): string
    {
        return (string) $this->code;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
