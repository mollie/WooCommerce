<?php
class WC_Mollie
{
    const PLUGIN_ID      = 'woocommerce-mollie-payments';
    const PLUGIN_VERSION = '2.0.0-alpha';

    /**
     * @var bool
     */
    private static $initiated = false;

    /**
     * @var array
     */
    public static $GATEWAYS = array(
        'WC_Mollie_Gateway_BankTransfer',
        'WC_Mollie_Gateway_Belfius',
        'WC_Mollie_Gateway_Bitcoin',
        'WC_Mollie_Gateway_Creditcard',
        'WC_Mollie_Gateway_Ideal',
        'WC_Mollie_Gateway_MisterCash',
        'WC_Mollie_Gateway_PayPal',
        'WC_Mollie_Gateway_Paysafecard',
        'WC_Mollie_Gateway_Sofort',
    );

    private function __construct () {}

    /**
     * Initialize plugin
     */
    public static function init ()
    {
        if (self::$initiated)
        {
            /*
             * Already initialized
             */
            return;
        }

        $plugin_basename = self::PLUGIN_ID . '/' . self::PLUGIN_ID . '.php';
        $settings_helper = self::getSettingsHelper();

        load_plugin_textdomain('woocommerce-mollie-payments', FALSE, self::PLUGIN_ID . '/i18n/languages');

        // Add global Mollie settings to 'WooCommerce -> Checkout -> Checkout Options'
        add_filter('woocommerce_payment_gateways_settings',   array($settings_helper, 'addGlobalSettingsFields'));
        // When page 'WooCommerce -> Checkout -> Checkout Options' is saved
        add_action('woocommerce_settings_save_checkout',      array($settings_helper, 'onGlobalSettingsSaved'));
        // Add Mollie gateways
        add_filter('woocommerce_payment_gateways',            array(__CLASS__, 'addGateways'));
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . $plugin_basename, array(__CLASS__, 'addPluginActionLinks'));
        // Listen to return URL call
        add_action('woocommerce_api_mollie_return',           array(__CLASS__, 'onMollieReturn'));
        // On order details
        add_action('woocommerce_order_details_after_order_table', array(__CLASS__, 'onOrderDetails'), 10, 1);

        // Mark plugin initiated
        self::$initiated = true;
    }

    /**
     * Payment return url callback
     */
    public static function onMollieReturn ()
    {
        $order_id = !empty($_GET['order_id']) ? $_GET['order_id'] : NULL;
        $key      = !empty($_GET['key']) ? $_GET['key'] : NULL;

        $order = wc_get_order($order_id);

        if (!$order)
        {
            header("Status: 404 Not Found");
            self::debug(__METHOD__ . ":  Could not find order $order_id.");
            return;
        }

        if (!$order->key_is_valid($key))
        {
            header('Status: 401 Unauthorized');
            self::debug(__METHOD__ . ":  Invalid key $key for order $order_id.");
            return;
        }

        $gateway = wc_get_payment_gateway_by_order($order);

        if (!$gateway)
        {
            header("Status: 404 Not Found");
            self::debug(__METHOD__ . ":  Could not find gateway for order $order_id.");
            return;
        }

        if (!($gateway instanceof WC_Mollie_Gateway_Abstract))
        {
            header('Status: 400 Bad Request');
            self::debug(__METHOD__ . ": Invalid gateway " . get_class($gateway) . " for this plugin. Order $order_id.");
            return;
        }

        /** @var WC_Mollie_Gateway_Abstract $gateway */

        $redirect_url = $gateway->getReturnRedirectUrlForOrder($order);

        self::debug(__METHOD__ . ": Redirect url on return order " . $gateway->id . ", order $order_id.");

        wp_redirect($redirect_url);
    }

    /**
     * @param WC_Order $order
     */
    public static function onOrderDetails (WC_Order $order)
    {
        $gateway = wc_get_payment_gateway_by_order($order);

        if (!$gateway || !($gateway instanceof WC_Mollie_Gateway_Abstract))
        {
            return;
        }

        /** @var WC_Mollie_Gateway_Abstract $gateway */

        $gateway->displayOrderDetails($order);
    }

    /**
     * Add Mollie gateways
     *
     * @param array $gateways
     * @return array
     */
    public static function addGateways (array $gateways)
    {
        return array_merge($gateways, self::$GATEWAYS);
    }

    /**
     * Add a WooCommerce notification message
     *
     * @param string $message Notification message
     * @param string $type One of notice, error or success (default notice)
     * @return $this
     */
    public static function addNotice ($message, $type = 'notice')
    {
        $type = in_array($type, array('notice','error','success')) ? $type : 'notice';

        // Check for existence of new notification api (WooCommerce >= 2.1)
        if (function_exists('wc_add_notice'))
        {
            wc_add_notice($message, $type);
        }
        else
        {
            $woocommerce = WooCommerce::instance();

            switch ($type)
            {
                case 'error' :
                    $woocommerce->add_error($message);
                    break;
                default :
                    $woocommerce->add_message($message);
                    break;
            }
        }
    }

    /**
     * Log messages to WooCommerce log
     *
     * @param mixed $message
     * @param bool  $set_debug_header Set X-Mollie-Debug header (default false)
     */
    public static function debug ($message, $set_debug_header = false)
    {
        // Convert message to string
        if (!is_string($message))
        {
            $message = print_r($message, true);
        }

        // Set debug header
        if ($set_debug_header && PHP_SAPI !== 'cli' && !headers_sent())
        {
            header("X-Mollie-Debug: $message");
        }

        // Log message
        if (self::getSettingsHelper()->isDebugEnabled())
        {
            static $logger;

            if (empty($logger))
            {
                // TODO: Use error_log() fallback if Wc_Logger is not available
                $logger = new WC_Logger();
            }

            $logger->add(self::PLUGIN_ID . '-' . date('Y-m-d'), $message);
        }
    }

    /**
     * Add plugin action links
     * @param array $links
     * @return array
     */
    public static function addPluginActionLinks (array $links)
    {
        return array_merge(
            array(
                // Add link to global Mollie settings
                '<a href="' . self::getSettingsHelper()->getGlobalSettingsUrl() . '">' . __('Mollie settings', 'woocommerce-mollie-payments') . '</a>',
                // Add link to WooCommerce logs
                '<a href="' . self::getSettingsHelper()->getLogsUrl() . '">' . __('Logs', 'woocommerce-mollie-payments') . '</a>'
            ),
            $links
        );
    }

    /**
     * @return WC_Mollie_Helper_Settings
     */
    public static function getSettingsHelper ()
    {
        static $settings_helper;

        if (!$settings_helper)
        {
            $settings_helper = new WC_Mollie_Helper_Settings();
        }

        return $settings_helper;
    }

    /**
     * @return WC_Mollie_Helper_Api
     */
    public static function getApiHelper ()
    {
        static $api_helper;

        if (!$api_helper)
        {
            $api_helper = new WC_Mollie_Helper_Api(self::getSettingsHelper());
        }

        return $api_helper;
    }

    /**
     * @return WC_Mollie_Helper_Data
     */
    public static function getDataHelper ()
    {
        static $data_helper;

        if (!$data_helper)
        {
            $data_helper = new WC_Mollie_Helper_Data(self::getApiHelper());
        }

        return $data_helper;
    }
}
