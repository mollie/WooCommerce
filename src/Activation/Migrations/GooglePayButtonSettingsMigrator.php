<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Activation\Migrations;

/**
 * An enabled Google Pay showed on the checkout only: it stays on there and is off on the cart until the merchant
 * switches it on. Otherwise the page switches stay never saved, so their default applies.
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
        $settings += [self::CHECKOUT => 'yes', self::CART => 'no'];
        update_option(self::OPTION, $settings);
    }
}
