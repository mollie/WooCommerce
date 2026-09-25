<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Types;

/**
 * A Mollie Checkout Session as the core sees it. The raw response is kept opaque, for public hooks only.
 */
final class ExpressSession
{
    private string $id;
    private string $status;
    private string $clientAccessToken;
    private string $expiresAt;
    private ?object $raw;
    public function __construct(string $id, string $status, string $clientAccessToken, string $expiresAt, ?object $raw = null)
    {
        $this->id = $id;
        $this->status = $status;
        $this->clientAccessToken = $clientAccessToken;
        $this->expiresAt = $expiresAt;
        $this->raw = $raw;
    }
    public function id(): string
    {
        return $this->id;
    }
    public function status(): string
    {
        return $this->status;
    }
    public function clientAccessToken(): string
    {
        return $this->clientAccessToken;
    }
    public function expiresAt(): string
    {
        return $this->expiresAt;
    }
    public function raw(): ?object
    {
        return $this->raw;
    }
}
