<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Activation\Migrations;

/**
 * A settings row never saved is left alone; a page switch already stored is kept.
 */
class GooglePayButtonSettingsMigrator implements MigratorInterface
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
        $settings = get_option(self::OPTION, false);
        if (!is_array($settings)) {
            return;
        }

        $settings += [
            self::CHECKOUT => ($settings['enabled'] ?? 'no') === 'yes' ? 'yes' : 'no',
            self::CART => 'no',
        ];

        update_option(self::OPTION, $settings);
    }
}
