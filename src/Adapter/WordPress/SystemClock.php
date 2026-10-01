<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Adapter\WordPress;

use Mollie\WooCommerce\Core\Clock;

final class SystemClock implements Clock
{
    public function now(): int
    {
        return time();
    }
}
