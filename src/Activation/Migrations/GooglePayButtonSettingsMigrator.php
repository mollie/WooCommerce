<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Activation\Migrations;

/**
 * Only an enabled Google Pay gets page switches; otherwise they stay never saved, so their default applies.
 */
class GooglePayButtonSettingsMigrator implements \Mollie\WooCommerce\Activation\Migrations\MigratorInterface
{
    private const OPTION = 'mollie_wc_gateway_googlepay_settings';
    private const CHECKOUT = 'mollie_googlepay_button_enabled_checkout';
    private const CART = 'mollie_googlepay_button_enabled_cart';
    public function targetVersion(): string
    {
        return '8.2.1';
    }
    public function migrate(): void
    {
        $settings = get_option(self::OPTION, \false);
        if (!is_array($settings) || ($settings['enabled'] ?? 'no') !== 'yes') {
            return;
        }
        $settings += [self::CHECKOUT => 'yes', self::CART => 'yes'];
        update_option(self::OPTION, $settings);
    }
}
