<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Workflow;

/**
 * What StartExpressSession answers: the token the browser needs and its expiry, or a refusal with a
 * stable code and the HTTP status the route answers with. Never anything else about the session.
 */
final class ExpressSessionResult
{
    private function __construct(private ?string $clientAccessToken, private ?string $expiresAt, private ?string $code, private int $httpStatus)
    {
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
