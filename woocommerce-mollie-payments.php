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
require_once 'includes/WC/Mollie/Autoload.php';

load_plugin_textdomain('woocommerce-mollie-payments', false, 'woocommerce-mollie-payments/i18n/languages');

/**
 * Called when plugin is loaded
 */
function wc_mollie_init ()
{
    // Register Mollie autoloader
    WC_Mollie_Autoload::register();

    // Setup and start plugin
    WC_Mollie::init();
}

/**
 * Called when plugin is activated
 */
function wc_mollie_activation_hook ()
{
    // WooCommerce plugin not activated
    if (!is_plugin_active('woocommerce/woocommerce.php'))
    {
        $title = sprintf(
            __('Could not activate plugin %s', 'woocommerce-mollie-payments'),
            'WooCommerce Mollie Payments'
        );
        $message = ''
            . '<h1><strong>' . $title . '</strong></h1><br/>'
            . 'WooCommerce plugin not activated. Please activate WooCommerce plugin first.';

        wp_die($message, $title, array('back_link' => true));
        return;
    }

    // Register Mollie autoloader
    WC_Mollie_Autoload::register();

    $plugin_status = new WC_Mollie_Helper_Status();

    if (!$plugin_status->isCompatible())
    {
        $title   = 'Could not activate WooCommerce Mollie Payments plugin';
        $message = '<h1><strong>Could not activate WooCommerce Mollie Payments plugin</strong></h1><br/>'
                 . implode('<br/>', $plugin_status->getErrors());

        wp_die($message, $title, array('back_link' => true));
        return;
    }
}

/**
 * Called when admin is initialised
 */
function wc_mollie_admin_init ()
{
    // WooCommerce plugin not activated
    if (!is_plugin_active('woocommerce/woocommerce.php'))
    {
        // Deactivate myself
        deactivate_plugins(plugin_basename(__FILE__));

        add_action('admin_notices', 'wc_mollie_deactivated');
    }
}

function wc_mollie_deactivated ()
{
    echo '<div class="error"><p>' . sprintf(__('%s deactivated because it depends on WooCommerce.', 'woocommerce-mollie-payments'), 'WooCommerce Mollie Payments') . '</p></div>';
}

register_activation_hook(__FILE__, 'wc_mollie_activation_hook');

add_action('admin_init', 'wc_mollie_admin_init');
add_action('init', 'wc_mollie_init');

/*
 * Info link
 * - WooCommerce hooks: http://docs.woothemes.com/wc-apidocs/hook-docs.html
 * - The WordPress / WooCommerce Hook/API Index: http://hookr.io/
 */
