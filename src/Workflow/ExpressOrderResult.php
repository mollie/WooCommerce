<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Workflow;

/**
 * What StartExpressOrder answers: the details the store holds for the shopper, handed to Mollie's
 * event.resolve(), or a refusal with a stable code, the HTTP status, and — when WooCommerce gave one —
 * WooCommerce's own reason for the shopper. Never the order id or key.
 */
final class ExpressOrderResult
{
    /**
     * @param array<string, string> $billing Mollie-shaped.
     * @param array<string, string> $shipping Mollie-shaped.
     */
    private function __construct(
        private ?string $code,
        private int $httpStatus,
        private array $billing = [],
        private array $shipping = [],
        private ?string $reason = null
    ) {
    }

    /**
     * @param array<string, string> $billing
     * @param array<string, string> $shipping
     */
    public static function ok(array $billing, array $shipping): self
    {
        return new self(null, 200, $billing, $shipping);
    }

    public static function refused(string $code, int $httpStatus, ?string $reason = null): self
    {
        return new self($code, $httpStatus, [], [], $reason);
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
     * @return array<string, string>
     */
    public function billing(): array
    {
        return $this->billing;
    }

    /**
     * @return array<string, string>
     */
    public function shipping(): array
    {
        return $this->shipping;
    }

    /**
     * WooCommerce's own words for a refusal it decided, e.g. an item out of stock.
     */
    public function reason(): ?string
    {
        return $this->reason;
    }
}
