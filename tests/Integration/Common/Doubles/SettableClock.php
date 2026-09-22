<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\Doubles;

use Mollie\WooCommerce\Core\Clock;

/**
 * The plugin's clock, pinned by a test: the real time until set, then whatever the test says.
 * Injected as the Clock::class override of bootExpress(), so a scenario can move the store past a
 * session's expiry, or an order past its expiry and grace, without waiting.
 */
final class SettableClock implements Clock
{
    private ?int $now = null;

    public function now(): int
    {
        return $this->now ?? time();
    }

    public function set(int $timestamp): void
    {
        $this->now = $timestamp;
    }
}
