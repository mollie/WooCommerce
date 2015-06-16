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

/**
 * Called when plugin is activated
 */
function wc_mollie_activation_hook ()
{
    $dependent_plugin = 'woocommerce/woocommerce.php';

    // WooCommerce plugin not activated
    if (!is_plugin_active($dependent_plugin) && !is_plugin_active_for_network($dependent_plugin))
    {
        $title = 'Could not activate plugin';
        $message = ''
            . '<h1><strong>Could not activate WooCommerce Mollie Payments plugin</strong></h1><br/>'
            . 'WooCommerce plugin not activated. Please activate WooCommerce plugin first.';

        wp_die($message, $title, array('back_link' => true));
        return;
    }

    // Register Mollie autoloader
    require_once 'includes/WC/Mollie/Autoload.php';
    WC_Mollie_Autoload::register();

    $checker = new WC_Mollie_Helper_CompatibilityChecker();

    try
    {
        $checker->checkCompatibility();
    }
    catch (WC_Mollie_Exception_IncompatiblePlatform $e)
    {
        $title = 'Could not activate WooCommerce Mollie Payments plugin';

        switch ($e->getCode())
        {
            case WC_Mollie_Exception_IncompatiblePlatform::API_CLIENT_NOT_INSTALLED:
                $message = 'Mollie API client not installed. Please make sure the plugin is installed correctly.';
                break;

            case WC_Mollie_Exception_IncompatiblePlatform::COULD_NOT_CONNECT_TO_MOLLIE:
                $title = 'Communicating with Mollie failed: ' . $e->getMessage();

                $message = ''
                . 'Please check the following conditions. You can ask your system administrator to help with this.'
                . '<ul>'
                . '<li>Make sure outside connections to <strong>' . esc_html(WC_Mollie_Helper_Api::getApiEndpoint()) . '</strong> are not blocked.</li>'
                . '<li>Make sure SSL v3 is disabled on your server. Mollie does not support SSL v3.</li>'
                . '<li>Make sure your server is up-to-date and the latest security patches have been installed.</li>'
                . '</ul><br/>'
                . 'Please contact <a href="mailto:info@mollie.com">info@mollie.com</a> if this still does not fix your problem.';
                break;

            default:
                $message = $e->getMessage();
                break;
        }

        $message = '<h1><strong>Could not activate WooCommerce Mollie Payments plugin</strong></h1><br/>' . $message;

        wp_die($message, $title, array('back_link' => true));
        return;
    }
}

/**
 * Called when plugin is loaded
 */
function wc_mollie_init ()
{
    $dependent_plugin = 'woocommerce/woocommerce.php';

    // WooCommerce plugin not activated
    if (!is_plugin_active($dependent_plugin) && !is_plugin_active_for_network($dependent_plugin))
    {
        // Deactivate myself
        deactivate_plugins(plugin_basename(__FILE__));

        return;
    }

    // Register Mollie autoloader
    require_once 'includes/WC/Mollie/Autoload.php';
    WC_Mollie_Autoload::register();

    // Setup and start plugin
    WC_Mollie::init();
}

register_activation_hook(__FILE__, 'wc_mollie_activation_hook');

add_action('init', 'wc_mollie_init');

/*
 * Info link
 * - WooCommerce hooks: http://docs.woothemes.com/wc-apidocs/hook-docs.html
 */
