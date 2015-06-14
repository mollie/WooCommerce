<?php
/**
 * Plugin Name: WooCommerce Mollie Payments
 * Plugin URI: https://www.mollie.com/
 * Description: Mollie payments for WooCommerce
 * Version: 2.0
 * Author: Mollie
 * Author URI: https://www.mollie.com
 * Requires at least: 3.0.1
 * Tested up to: 4.3
 * Text Domain: woocommerce-mollie-payments
 * Domain Path: /i18n/languages/
 * License: http://www.opensource.org/licenses/bsd-license.php  Berkeley Software Distribution License (BSD-License 2)
 */

register_activation_hook(__FILE__, function () {
    $dependent_plugin = 'woocommerce/woocommerce.php';

    if (!is_plugin_active($dependent_plugin) && !is_plugin_active_for_network($dependent_plugin))
    {
        $error = 'Could not enable WooCommerce Mollie Payments, enable WooCommerce plugin first.';
        $title = 'WooCommerce plugin not active';

        wp_die($error, $title, array('back_link' => true));
        return;
    }
});

add_action('init', function () {
    $dependent_plugin = 'woocommerce/woocommerce.php';

    // WooCommerce plugin not activated
    if (!is_plugin_active($dependent_plugin) && !is_plugin_active_for_network($dependent_plugin))
    {
        // Disable myself
        deactivate_plugins(plugin_basename(__FILE__));

        return;
    }

    // Register Mollie autoloader
    require_once 'includes/WC/Mollie/Autoload.php';
    WC_Mollie_Autoload::register();

    // Setup and start plugin
    WC_Mollie::init();
});


/*
 * Info link
 * - WooCommerce hooks: http://docs.woothemes.com/wc-apidocs/hook-docs.html
 */
