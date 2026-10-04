<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Flow;

/**
 * Never carries the order id, its key or shopper data.
 */
final class ExpressOrderResult
{
    private function __construct(private ?string $code, private int $httpStatus, private ?string $reason = null)
    {
    }
    public static function ok(): self
    {
        return new self(null, 200);
    }
    public static function refused(string $code, int $httpStatus, ?string $reason = null): self
    {
        return new self($code, $httpStatus, $reason);
    }
    public function isOk(): bool
    {
        return $this->code === null;
    }
    public function code(): string
    {
        return (string) $this->code;
    }
    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
    /**
     * WooCommerce's own message, e.g. an item out of stock.
     */
    public function reason(): ?string
    {
        return $this->reason;
    }
}
