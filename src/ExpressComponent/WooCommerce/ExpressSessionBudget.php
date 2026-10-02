<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

use WC_Rate_Limiter;

/**
 * Keyed on user id or the hashed caller address, not the cookie, so dropping the session does not
 * reset it. WC_Rate_Limiter allows one action per delay, hence one slot per allowed session.
 */
class ExpressSessionBudget
{
    private const KEY_PREFIX = 'mollie_express_session_';

    public function __construct(
        private int $maxNewSessions,
        private int $windowSeconds
    ) {
    }

    /** False when the window's budget is spent. */
    public function take(string $callerAddress): bool
    {
        $client = $this->clientKey($callerAddress);
        for ($slot = 0; $slot < $this->maxNewSessions; $slot++) {
            $actionId = self::KEY_PREFIX . $client . '_' . $slot;
            if (!WC_Rate_Limiter::retried_too_soon($actionId)) {
                WC_Rate_Limiter::set_rate_limit($actionId, $this->windowSeconds);

                return true;
            }
        }

        return false;
    }

    private function clientKey(string $callerAddress): string
    {
        $userId = get_current_user_id();
        $caller = $userId > 0 ? 'user:' . $userId : 'ip:' . $callerAddress;

        return substr(hash_hmac('sha256', $caller, wp_salt('nonce')), 0, 24);
    }
}
