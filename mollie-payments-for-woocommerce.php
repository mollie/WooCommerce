<?php
/**
 * Plugin Name: Mollie Payments for WooCommerce
 * Plugin URI: https://www.mollie.com
 * Description: Accept payments in WooCommerce with the official Mollie plugin
 * Version: 6.1.0
 * Author: Mollie
 * Author URI: https://www.mollie.com
 * Requires at least: 3.8
 * Tested up to: 5.6
 * Text Domain: mollie-payments-for-woocommerce
 * Domain Path: /languages
 * License: GPLv2 or later
 * WC requires at least: 2.2.0
 * WC tested up to: 4.9
 */

use Mollie\Api\CompatibilityChecker;

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
    require_once __DIR__ . '/src/subscriptions_status_check_functions.php';

    if (!mollie_wc_plugin_autoload()) {
        return;
    }

    if (!mollieIsWooCommerceCompatible()) {
        add_action('admin_notices', 'mollie_wc_plugin_inactive');
        return;
    }

    $status_helper = Mollie_WC_Plugin::getStatusHelper();

    if (!$status_helper->isCompatible()) {
        $title = 'Could not activate plugin ' . Mollie_WC_Plugin::PLUGIN_TITLE;
        $message = '<h1><strong>Could not activate plugin ' . Mollie_WC_Plugin::PLUGIN_TITLE . '</strong></h1><br/>'
            . implode('<br/>', $status_helper->getErrors());

        wp_die($message, $title, array('back_link' => true));
        return;
    }

    mollieDeleteWPTranslationFiles();
}


function mollieWcShowWcDroppingNotice()
{
    if (get_option('mollie-payments-for-woocommerce-wc-drop')) {
        return false;
    }
    $notice = new Mollie_WC_Notice_AdminNotice();
    $message = sprintf(
        esc_html__(
            '%1$sMollie Payments for WooCommerce is dropping WooCommerce support for version 2.x.%2$s Please %3$supdate WooCommerce to version 3.0 or newer &raquo;%4$s. %5$sContact our support team if any questions remain%6$s',
            'mollie-payments-for-woocommerce'
        ),
        '<strong>',
        '</strong>',
        '<a href="' . esc_url(admin_url('plugins.php')) . '">',
        '</a>',
        '<a href="' . esc_url('https://mollie.inpsyde.com/docs/how-to-request-support-via-website-widget/') . '">',
        '</a>'
    );
    if (version_compare(get_option('woocommerce_version'), '3.0', '<')) {
        $notice->addAdminNotice('notice-error is-dismissible', $message);
    }

    update_option('mollie-payments-for-woocommerce-wc-drop', 'yes', true);
    return false;
}


function mollieDeleteWPTranslationFiles()
{
    WP_Filesystem();
    global $wp_filesystem;

    $remote_destination = $wp_filesystem->find_folder(WP_LANG_DIR);
    if (!$wp_filesystem->exists($remote_destination)) {
        return;
    }
    $languageExtensions = [
        'de_DE',
        'de_DE_formal',
        'es_ES',
        'fr_FR',
        'it_IT',
        'nl_BE',
        'nl_NL',
        'nl_NL_formal'
    ];
    $translationExtensions = ['.mo', '.po'];
    $destination = WP_LANG_DIR
        . '/plugins/mollie-payments-for-woocommerce-';
    foreach ($languageExtensions as $languageExtension) {
        foreach ($translationExtensions as $translationExtension) {
            $file = $destination . $languageExtension
                . $translationExtension;
            $wp_filesystem->delete($file, false);
        }
    }
}

function mollieIsWooCommerceCompatible()
{
    $wooCommerceVersion = get_option('woocommerce_version');
    $isWooCommerceVersionCompatible = version_compare(
        $wooCommerceVersion,
        Mollie_WC_Helper_Status::MIN_WOOCOMMERCE_VERSION,
        '>='
    );

    return class_exists('WooCommerce') && $isWooCommerceVersionCompatible;
}

function mollie_wc_plugin_inactive_json_extension()
{
    $nextScheduledTime = wp_next_scheduled('pending_payment_confirmation_check');
    if ($nextScheduledTime) {
        wp_unschedule_event($nextScheduledTime, 'pending_payment_confirmation_check');
    }

    if (!is_admin()) {
        return false;
    }

    echo '<div class="error"><p>';
    echo esc_html__(
        'Mollie Payments for WooCommerce requires the JSON extension for PHP. Enable it in your server or ask your webhoster to enable it for you.',
        'mollie-payments-for-woocommerce'
    );
    echo '</p></div>';

    return false;
}

function mollie_wc_plugin_inactive_php()
{
    $nextScheduledTime = wp_next_scheduled('pending_payment_confirmation_check');
    if ($nextScheduledTime) {
        wp_unschedule_event($nextScheduledTime, 'pending_payment_confirmation_check');
    }

    if (!is_admin()) {
        return false;
    }

    echo '<div class="error"><p>';
    echo sprintf(
        esc_html__(
            'Mollie Payments for WooCommerce 4.0 requires PHP 5.6 or higher. Your PHP version is outdated. Upgrade your PHP version and view %sthis FAQ%s.',
            'mollie-payments-for-woocommerce'
        ),
        '<a href="https://github.com/mollie/WooCommerce/wiki/PHP-&-Mollie-API-v2" target="_blank">',
        '</a>'
    );
    echo '</p></div>';

    return false;
}

function mollie_wc_plugin_inactive()
{
    $nextScheduledTime = wp_next_scheduled('pending_payment_confirmation_check');
    if ($nextScheduledTime) {
        wp_unschedule_event($nextScheduledTime, 'pending_payment_confirmation_check');
    }

    if (!is_admin()) {
        return false;
    }

    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        echo '<div class="error"><p>';
        echo sprintf(
            esc_html__(
                '%1$sMollie Payments for WooCommerce is inactive.%2$s The %3$sWooCommerce plugin%4$s must be active for it to work. Please %5$sinstall & activate WooCommerce &raquo;%6$s',
                'mollie-payments-for-woocommerce'
            ),
            '<strong>',
            '</strong>',
            '<a href="https://wordpress.org/plugins/woocommerce/">',
            '</a>',
            '<a href="' . esc_url(admin_url('plugins.php')) . '">',
            '</a>'
        );
        echo '</p></div>';
        return false;
    }

    if (version_compare(get_option('woocommerce_version'), '2.2', '<')) {
        echo '<div class="error"><p>';
        echo sprintf(
            esc_html__(
                '%1$sMollie Payments for WooCommerce is inactive.%2$s This version requires WooCommerce 2.2 or newer. Please %3$supdate WooCommerce to version 2.2 or newer &raquo;%4$s',
                'mollie-payments-for-woocommerce'
            ),
            '<strong>',
            '</strong>',
            '<a href="' . esc_url(admin_url('plugins.php')) . '">',
            '</a>'
        );
        echo '</p></div>';
        return false;
    }
}

function mollieWcNoticeApiKeyMissing(){
    //if test/live keys are in db return
    $liveKeySet = get_option('mollie-payments-for-woocommerce_live_api_key');
    $testKeySet = get_option('mollie-payments-for-woocommerce_test_api_key');
    $apiKeysSetted = $liveKeySet || $testKeySet;
    if ($apiKeysSetted) {
        return;
    }

    $notice = new Mollie_WC_Notice_AdminNotice();
    $message = sprintf(
        esc_html__(
            '%1$sMollie Payments for WooCommerce: API keys missing%2$s Please%3$s set your API keys here%4$s.',
            'mollie-payments-for-woocommerce'
        ),
        '<strong>',
        '</strong>',
        '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=mollie_settings')) . '">',
        '</a>'
    );

    $notice->addAdminNotice('notice-error is-dismissible', $message);
}

function mollie_wc_plugin_autoload()
{
    $autoloader = __DIR__ . '/vendor/autoload.php';
    if (file_exists($autoloader)) {
        /** @noinspection PhpIncludeInspection */
        require $autoloader;
    }

    return class_exists(Mollie_WC_Plugin::class);
}

$bootstrap = Closure::bind(
    function () {
        add_action(
            'plugins_loaded',
            function () {
                require_once __DIR__ . '/inc/functions.php';
                require_once __DIR__ . '/src/subscriptions_status_check_functions.php';

                if (!mollie_wc_plugin_autoload()) {
                    return;
                }

                if (function_exists('extension_loaded') && !extension_loaded('json')) {
                    add_action('admin_notices', 'mollie_wc_plugin_inactive_json_extension');
                    return;
                }

                if (version_compare(PHP_VERSION, CompatibilityChecker::MIN_PHP_VERSION, '<')) {
                    add_action('admin_notices', 'mollie_wc_plugin_inactive_php');
                    return;
                }

                if (!mollieIsWooCommerceCompatible()) {
                    add_action('admin_notices', 'mollie_wc_plugin_inactive');
                    return;
                }
                mollieWcShowWcDroppingNotice();

                add_action(
                    'init',
                    function () {
                        load_plugin_textdomain('mollie-payments-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');
                        Mollie_WC_Plugin::init();
                    }
                );
                
                add_action( 'core_upgrade_preamble', 'mollieDeleteWPTranslationFiles' );
                add_filter(
                    'site_transient_update_plugins',
                    function ($value) {
                        if (isset($value->translations)) {
                            $i = 0;
                            foreach ($value->translations as $translation) {
                                if ($translation["slug"]
                                    == "mollie-payments-for-woocommerce"
                                ) {
                                    unset($value->translations[$i]);
                                }
                                $i++;
                            }
                        }

                        return $value;
                    }
                );
                mollieWcNoticeApiKeyMissing();
            }
        );
    },
    null
);

$bootstrap();

register_activation_hook(M4W_FILE, 'mollie_wc_plugin_activation_hook');
