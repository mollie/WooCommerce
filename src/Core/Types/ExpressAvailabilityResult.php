<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Types;

/**
 * May the Express Component run: available, blocked until the shopper acts, or unavailable.
 * The reason is what the admin notice, the shopper message and the event log are built from.
 */
final class ExpressAvailabilityResult
{
    public const AVAILABLE = 'available';
    public const BLOCKED = 'blocked';
    public const UNAVAILABLE = 'unavailable';

    private function __construct(
        private string $status,
        private ?string $reason
    ) {
    }

    public static function available(): self
    {
        return new self(self::AVAILABLE, null);
    }

    public static function blocked(string $reason): self
    {
        return new self(self::BLOCKED, $reason);
    }

    public static function unavailable(string $reason): self
    {
        return new self(self::UNAVAILABLE, $reason);
    }

    /**
     * @return 'available'|'blocked'|'unavailable'
     */
    public function status(): string
    {
        return $this->status;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }
}
