<?php

/**
 * Plugin Name: Mollie Payments for WooCommerce
 * Plugin URI: https://www.mollie.com
 * Description: Accept payments in WooCommerce with the official Mollie plugin
 * Version: 8.1.3
 * Author: Mollie
 * Author URI: https://www.mollie.com
 * Requires at least: 5.0
 * Tested up to: 6.9
 * Text Domain: mollie-payments-for-woocommerce
 * Domain Path: /languages
 * License: GPLv2 or later
 * WC requires at least: 3.9
 * WC tested up to: 10.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */
declare (strict_types=1);
namespace Mollie\WooCommerce;

use Mollie\Psr\Container\ContainerInterface;
use Throwable;
require_once \ABSPATH . 'wp-admin/includes/plugin.php';
define('M4W_FILE', __FILE__);
define('M4W_PLUGIN_DIR', dirname(\M4W_FILE));
// Plugin folder URL.
if (!defined('M4W_PLUGIN_URL')) {
    define('M4W_PLUGIN_URL', plugin_dir_url(\M4W_FILE));
}
function mollie_wc_plugin_autoload(): bool
{
    $autoloader = __DIR__ . '/vendor/autoload.php';
    $mollieSdkAutoload = __DIR__ . '/vendor/mollie/mollie-api-php/vendor/autoload.php';
    if (file_exists($autoloader) && !class_exists('Mollie\WooCommerce\Activation\ActivationModule')) {
        require $autoloader;
    }
    if (file_exists($mollieSdkAutoload)) {
        /**
         * @psalm-suppress MissingFile
         */
        require $mollieSdkAutoload;
    }
    return \true;
}
/**
 * Display an error message in the WP admin.
 *
 * @param string $message The message content
 *
 * @return void
 */
function errorNotice(string $message)
{
    add_action('all_admin_notices', static function () use ($message) {
        $class = 'notice notice-error';
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
    });
}
/**
 * Handle any exception that might occur during plugin setup.
 *
 * @param Throwable $throwable The Exception
 *
 * @return void
 */
function handleException(Throwable $throwable)
{
    do_action('inpsyde.mollie-woocommerce.critical', $throwable);
    errorNotice(sprintf('<strong>Error:</strong> %s <br><pre>%s</pre>', $throwable->getMessage(), $throwable->getTraceAsString()));
}
/**
 * Initialize all the plugin things.
 *
 * @return ContainerInterface|null
 */
function initialize(): ?ContainerInterface
{
    static $container = null;
    $root_dir = \M4W_PLUGIN_DIR;
    if ($container === null) {
        try {
            $bootstrap = require __DIR__ . '/bootstrap.php';
            $container = $bootstrap($root_dir);
        } catch (Throwable $throwable) {
            handleException($throwable);
            return null;
        }
    }
    return $container;
}
add_action(
    /**
     * @throws Throwable
     */
    'plugins_loaded',
    static function () {
        initialize();
    }
);
