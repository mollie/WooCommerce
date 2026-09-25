<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Payment;

/**
 * Whether the action mollie_woocommerce_cancel_unpaid_orders must be scheduled.
 *
 * It is needed while an enabled gateway has the expiry setting on, read exactly as
 * PaymentModule::IsExpiryDateEnabled() always read it (settings whose "enabled" is anything but
 * 'yes' are skipped; settings with no "enabled" key still count), or while Express is enabled,
 * because the express cleanup runs on the same action.
 */
final class CancelUnpaidSchedule
{
    /**
     * @param array<int, mixed> $gatewaySettings Each gateway's stored settings, as get_option() returns them.
     */
    public static function needed(array $gatewaySettings, bool $expressEnabled): bool
    {
        return $expressEnabled || self::expiryEnabled($gatewaySettings);
    }

    /**
     * @param array<int, mixed> $gatewaySettings
     */
    private static function expiryEnabled(array $gatewaySettings): bool
    {
        foreach ($gatewaySettings as $option) {
            if (!empty($option) && isset($option['enabled']) && $option['enabled'] !== 'yes') {
                continue;
            }
            if (!empty($option['activate_expiry_days_setting']) && $option['activate_expiry_days_setting'] === 'yes') {
                return true;
            }
        }

        return false;
    }
}
