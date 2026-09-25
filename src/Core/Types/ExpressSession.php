<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Types;

/**
 * A Mollie Checkout Session as the core sees it: plain values, no SDK object.
 */
final class ExpressSession
{
    public function __construct(private string $id, private string $status, private string $clientAccessToken, private string $expiresAt)
    {
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
}
