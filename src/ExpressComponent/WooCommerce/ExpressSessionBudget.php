<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

use WC_Rate_Limiter;
/**
 * Two budgets, the shopper's and the caller address's; both must have room.
 * WC_Rate_Limiter allows one action per delay, hence one slot per session.
 */
class ExpressSessionBudget
{
    public const TAKEN = 'taken';
    public const SHOPPER_SPENT = 'shopper';
    public const ADDRESS_SPENT = 'address';
    private const KEY_PREFIX = 'mollie_express_session_';
    public function __construct(private int $maxNewSessions, private int $maxNewSessionsPerAddress, private int $windowSeconds)
    {
    }
    /**
     * @return self::TAKEN|self::SHOPPER_SPENT|self::ADDRESS_SPENT
     */
    public function take(string $callerAddress): string
    {
        $address = 'ip:' . $callerAddress;
        $shopperSlot = $this->freeSlot($this->shopper() ?? $address, $this->maxNewSessions);
        if ($shopperSlot === null) {
            return self::SHOPPER_SPENT;
        }
        $addressSlot = $this->freeSlot($address, $this->maxNewSessionsPerAddress);
        if ($addressSlot === null) {
            return self::ADDRESS_SPENT;
        }
        // Both or neither: a refusal spends nothing.
        WC_Rate_Limiter::set_rate_limit($shopperSlot, $this->windowSeconds);
        WC_Rate_Limiter::set_rate_limit($addressSlot, $this->windowSeconds);
        return self::TAKEN;
    }
    /**
     * True once per window for the whole shop.
     */
    public function refusalIsNews(): bool
    {
        $actionId = self::KEY_PREFIX . 'refusal_reported';
        if (WC_Rate_Limiter::retried_too_soon($actionId)) {
            return \false;
        }
        WC_Rate_Limiter::set_rate_limit($actionId, $this->windowSeconds);
        return \true;
    }
    private function shopper(): ?string
    {
        $userId = get_current_user_id();
        if ($userId > 0) {
            return 'user:' . $userId;
        }
        $session = function_exists('WC') && WC()->session instanceof \WC_Session ? WC()->session : null;
        $customer = $session ? (string) $session->get_customer_id() : '';
        return $customer !== '' ? 'session:' . $customer : null;
    }
    private function freeSlot(string $owner, int $slots): ?string
    {
        $key = self::KEY_PREFIX . substr(hash_hmac('sha256', $owner, wp_salt('nonce')), 0, 24) . '_';
        for ($slot = 0; $slot < $slots; $slot++) {
            if (!WC_Rate_Limiter::retried_too_soon($key . $slot)) {
                return $key . $slot;
            }
        }
        return null;
    }
}
