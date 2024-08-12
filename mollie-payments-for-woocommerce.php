<?php
/**
 * Plugin Name: Mollie Payments for WooCommerce
 * Plugin URI: https://www.mollie.com
 * Description: Accept payments in WooCommerce with the official Mollie plugin
 * Version: 7.7.0
 * Author: Mollie
 * Author URI: https://www.mollie.com
 * Requires at least: 5.0
 * Tested up to: 6.6
 * Text Domain: mollie-payments-for-woocommerce
 * Domain Path: /languages
 * License: GPLv2 or later
 * WC requires at least: 3.9
 * WC tested up to: 9.1
 * Requires PHP: 7.2
 * Requires Plugins: woocommerce
 */
declare(strict_types=1);

namespace Mollie\WooCommerce;

use Mollie\WooCommerce\MerchantCapture\MerchantCaptureModule;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Package;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Properties\PluginProperties;
use Mollie\WooCommerce\Activation\ActivationModule;
use Mollie\WooCommerce\Activation\ConstraintsChecker;
use Mollie\WooCommerce\Assets\AssetsModule;
use Mollie\WooCommerce\Shared\SharedModule;
use Mollie\WooCommerce\Gateway\GatewayModule;
use Mollie\WooCommerce\Gateway\Voucher\VoucherModule;
use Mollie\WooCommerce\Log\LogModule;
use Mollie\WooCommerce\Notice\NoticeModule;
use Mollie\WooCommerce\Payment\PaymentModule;
use Mollie\WooCommerce\SDK\SDKModule;
use Mollie\WooCommerce\Settings\SettingsModule;
use Mollie\WooCommerce\Uninstall\UninstallModule;
use Throwable;

require_once(ABSPATH . 'wp-admin/includes/plugin.php');

define('M4W_FILE', __FILE__);
define('M4W_PLUGIN_DIR', dirname(M4W_FILE));

// Plugin folder URL.
if (!defined('M4W_PLUGIN_URL')) {
    define('M4W_PLUGIN_URL', plugin_dir_url(M4W_FILE));
}

/**
 * Called when plugin is activated
 */
function mollie_wc_plugin_activation_hook()
{
    require_once __DIR__ . '/inc/functions.php';

    if (!mollie_wc_plugin_autoload()) {
        return;
    }

    mollieDeleteWPTranslationFiles();
}


function mollie_wc_plugin_autoload()
{
    $autoloader = __DIR__ . '/vendor/autoload.php';
    $mollieSdkAutoload = __DIR__ . '/vendor/mollie/mollie-api-php/vendor/autoload.php';
    if (file_exists($autoloader)) {
        /**
         * @noinspection PhpIncludeInspection
         *
         */
        require $autoloader;
    }

    if (file_exists($mollieSdkAutoload)) {
        /**
         * @noinspection PhpIncludeInspection
         * @psalm-suppress MissingFile
         */
        require $mollieSdkAutoload;
    }
    return true;
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
    add_action(
        'all_admin_notices',
        static function () use ($message) {
            $class = 'notice notice-error';
            printf(
                '<div class="%1$s"><p>%2$s</p></div>',
                esc_attr($class),
                wp_kses_post($message)
            );
        }
    );
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

    errorNotice(
        sprintf(
            '<strong>Error:</strong> %s <br><pre>%s</pre>',
            $throwable->getMessage(),
            $throwable->getTraceAsString()
        )
    );
}

/**
 * Initialize all the plugin things.
 *
 * @throws Throwable
 */
function initialize()
{
    try {
        require_once __DIR__ . '/inc/functions.php';

        if (!mollie_wc_plugin_autoload()) {
            return;
        }

        $checker = new ConstraintsChecker();
        $meetRequirements = $checker->handleActivation();
        if (!$meetRequirements) {
            $nextScheduledTime = wp_next_scheduled('pending_payment_confirmation_check');
            if ($nextScheduledTime) {
                wp_unschedule_event($nextScheduledTime, 'pending_payment_confirmation_check');
            }
            return;
        }
        // Initialize plugin.
        $properties = PluginProperties::new(__FILE__);
        $bootstrap = Package::new($properties);
        $modules = [
            new ActivationModule(__FILE__, $properties->get('version')),
            new NoticeModule(),
            new SharedModule(),
            new SDKModule(),
            new SettingsModule(),
            new LogModule('mollie-payments-for-woocommerce-'),
            new AssetsModule(),
            new GatewayModule(),
            new VoucherModule(),
            new PaymentModule(),
            new MerchantCaptureModule(),
            new UninstallModule(),
        ];
        $modules = apply_filters('mollie_wc_plugin_modules', $modules);
        $bootstrap->boot(...$modules);
    } catch (Throwable $throwable) {
        handleException($throwable);
    }
}

add_action('plugins_loaded', __NAMESPACE__ . '\\initialize');

register_activation_hook(M4W_FILE, __NAMESPACE__ . '\mollie_wc_plugin_activation_hook');
