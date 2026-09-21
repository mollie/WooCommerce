<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core;

/**
 * The only way core and workflows learn the time, so tests can pass a fixed one.
 */
interface Clock
{
    /**
     * Seconds since the Unix epoch.
     */
    public function now(): int;
}
