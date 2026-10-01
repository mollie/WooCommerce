<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Workflow;

/**
 * What StartExpressOrder answers: that the order exists, so the browser may call Mollie's
 * event.resolve() with nothing, or a refusal with a stable code, the HTTP status, and — when
 * WooCommerce gave one — WooCommerce's own reason for the shopper. Never the order id, its key or
 * anything about the shopper: the wallet governs the contact and billing details.
 */
final class ExpressOrderResult
{
    private function __construct(
        private ?string $code,
        private int $httpStatus,
        private ?string $reason = null
    ) {
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
     * WooCommerce's own words for a refusal it decided, e.g. an item out of stock.
     */
    public function reason(): ?string
    {
        return $this->reason;
    }
}
