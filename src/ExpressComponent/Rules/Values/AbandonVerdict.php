<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules\Values;

final class AbandonVerdict
{
    private function __construct(
        private bool $cancels,
        private string $reason
    ) {
    }

    public static function cancel(string $reason): self
    {
        return new self(true, $reason);
    }

    public static function keep(string $reason): self
    {
        return new self(false, $reason);
    }

    public function cancels(): bool
    {
        return $this->cancels;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
