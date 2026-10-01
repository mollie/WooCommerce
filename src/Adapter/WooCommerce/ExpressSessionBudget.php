<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Adapter\WooCommerce;

use WC_Geolocation;
use WC_Rate_Limiter;

/**
 * The anonymous work budget of the session route: at most maxNewSessions new Mollie sessions per
 * windowSeconds for one caller.
 *
 * Kept by WooCommerce's WC_Rate_Limiter, keyed on something the caller cannot choose — the user id,
 * else the client IP, as the Store API does — so dropping the session cookie does not reset it. The
 * limiter allows one action per delay, so the budget is maxNewSessions slots, each busy for the
 * window once taken. Only the hash of the IP is stored.
 */
class ExpressSessionBudget
{
    private const KEY_PREFIX = 'mollie_express_session_';

    public function __construct(
        private int $maxNewSessions,
        private int $windowSeconds
    ) {
    }

    /**
     * Takes one new session from the budget. False when the budget of the window is spent.
     */
    public function take(): bool
    {
        $client = $this->clientKey();
        for ($slot = 0; $slot < $this->maxNewSessions; $slot++) {
            $actionId = self::KEY_PREFIX . $client . '_' . $slot;
            if (!WC_Rate_Limiter::retried_too_soon($actionId)) {
                WC_Rate_Limiter::set_rate_limit($actionId, $this->windowSeconds);

                return true;
            }
        }

        return false;
    }

    private function clientKey(): string
    {
        $userId = get_current_user_id();
        $caller = $userId > 0 ? 'user:' . $userId : 'ip:' . WC_Geolocation::get_ip_address();

        return substr(hash_hmac('sha256', $caller, wp_salt('nonce')), 0, 24);
    }
}
