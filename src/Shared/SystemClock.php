<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Shared;

final class SystemClock implements Clock
{
    public function now(): int
    {
        return time();
    }
}
