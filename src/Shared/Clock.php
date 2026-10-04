<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Shared;

/**
 * Injected so tests can pass a fixed time.
 */
interface Clock
{
    /**
     * Unix timestamp.
     */
    public function now(): int;
}
