<?php
/**
 * Plugin Name: Mollie Payments for WooCommerce
 * Plugin URI: https://github.com/mollie/WooCommerce
 * Description: Accept payments in WooCommerce with the official Mollie plugin
 * Version: 2.7.0
 * Author: Mollie
 * Author URI: https://www.mollie.com
 * Requires at least: 3.8
 * Tested up to: 4.8
 * Text Domain: mollie-payments-for-woocommerce
 * Domain Path: /i18n/languages/
 * License: GPLv2 or later
 * WC requires at least: 2.1.0
 * WC tested up to: 3.2.0
 */
require_once 'includes/mollie/wc/autoload.php';

// TODO: Add more constants WP-style, and move from classes to here.

// Plugin folder URL.
if ( ! defined( 'M4W_PLUGIN_URL' ) ) {
	define( 'M4W_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Plugin directory
if ( ! defined( 'M4W_PLUGIN_DIR' ) ) {
	define( 'M4W_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Called when plugin is loaded
 */
function mollie_wc_plugin_init ()
{
    if (!class_exists('WooCommerce'))
    {
        /*
         * Plugin depends on WooCommerce
         * is_plugin_active() is not available yet :(
         */
        return;
    }

    // Register Mollie autoloader
    Mollie_WC_Autoload::register();

    // Setup and start plugin
    Mollie_WC_Plugin::init();
}

/**
 * Called when plugin is activated
 */
function mollie_wc_plugin_activation_hook ()
{
    // WooCommerce plugin not activated
    if (!is_plugin_active('woocommerce/woocommerce.php'))
    {
        $title = sprintf(
            __('Could not activate plugin %s', 'mollie-payments-for-woocommerce'),
            'Mollie Payments for WooCommerce'
        );
        $message = ''
            . '<h1><strong>' . $title . '</strong></h1><br/>'
            . 'WooCommerce plugin not activated. Please activate WooCommerce plugin first.';

        wp_die($message, $title, array('back_link' => true));
        return;
    }

    // Register Mollie autoloader
    Mollie_WC_Autoload::register();

    $status_helper = Mollie_WC_Plugin::getStatusHelper();

    if (!$status_helper->isCompatible())
    {
        $title   = 'Could not activate plugin ' . Mollie_WC_Plugin::PLUGIN_TITLE;
        $message = '<h1><strong>Could not activate plugin ' . Mollie_WC_Plugin::PLUGIN_TITLE . '</strong></h1><br/>'
                 . implode('<br/>', $status_helper->getErrors());

        wp_die($message, $title, array('back_link' => true));
        return;
    }
}

/**
 * Called when admin is initialised
 */
function mollie_wc_plugin_admin_init ()
{
    // WooCommerce plugin not activated
    if (!is_plugin_active('woocommerce/woocommerce.php'))
    {
        // Deactivate myself
        deactivate_plugins(plugin_basename(__FILE__));

        add_action('admin_notices', 'mollie_wc_plugin_deactivated');
    }
}

function mollie_wc_plugin_deactivated ()
{
    $nextScheduledTime = wp_next_scheduled( 'pending_payment_confirmation_check' ) ;
    if ($nextScheduledTime) {
        wp_unschedule_event( $nextScheduledTime, 'pending_payment_confirmation_check' );
    }
    echo '<div class="error"><p>' . sprintf(__('%s deactivated because it depends on WooCommerce.', 'mollie-payments-for-woocommerce'), Mollie_WC_Plugin::PLUGIN_TITLE) . '</p></div>';
}

register_activation_hook(__FILE__, 'mollie_wc_plugin_activation_hook');

add_action('admin_init', 'mollie_wc_plugin_admin_init');
add_action('init', 'mollie_wc_plugin_init');

/**
 * Load the plugin text domain for translations.
 */
function mollie_add_plugin_textdomain() {

	load_plugin_textdomain( 'mollie-payments-for-woocommerce', false, M4W_PLUGIN_DIR . 'i18n/languages/' );

}

add_action( 'plugins_loaded', 'mollie_add_plugin_textdomain' );
