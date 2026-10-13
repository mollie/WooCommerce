<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment\Rules;

/**
 * Whether mollie_woocommerce_cancel_unpaid_orders must be scheduled; the express cleanup runs on it too.
 */
final class CancelUnpaidSchedule
{
    /**
     * @param array<int, mixed> $gatewaySettings Each gateway's get_option() value.
     */
    public static function needed(array $gatewaySettings, bool $expressCleanup): bool
    {
        return $expressCleanup || self::expiryEnabled($gatewaySettings);
    }
    /**
     * @param array<int, mixed> $gatewaySettings
     */
    private static function expiryEnabled(array $gatewaySettings): bool
    {
        foreach ($gatewaySettings as $option) {
            // Settings without an "enabled" key still count.
            if (!empty($option) && isset($option['enabled']) && $option['enabled'] !== 'yes') {
                continue;
            }
            if (!empty($option['activate_expiry_days_setting']) && $option['activate_expiry_days_setting'] === 'yes') {
                return \true;
            }
        }
        return \false;
    }
}
