<?php
// Require WooCommerce fallback functions
require_once dirname(dirname(dirname(__FILE__))) . '/woocommerce_functions.php';
require_once dirname(dirname(dirname(__FILE__))) . '/subscriptions_status_check_functions.php';

class Mollie_WC_Plugin
{
    const PLUGIN_ID      = 'mollie-payments-for-woocommerce';
    const PLUGIN_TITLE   = 'Mollie Payments for WooCommerce';
    const PLUGIN_VERSION = '5.0.7';

    const DB_VERSION     = '1.0';
    const DB_VERSION_PARAM_NAME = 'mollie-db-version';
    const PENDING_PAYMENT_DB_TABLE_NAME = 'mollie_pending_payment';

    /**
     * @var bool
     */
    private static $initiated = false;

    /**
     * @var array
     */
    public static $GATEWAYS = array(
        'Mollie_WC_Gateway_BankTransfer',
        'Mollie_WC_Gateway_Belfius',
        'Mollie_WC_Gateway_Bitcoin',
        'Mollie_WC_Gateway_Creditcard',
        'Mollie_WC_Gateway_DirectDebit',
        'Mollie_WC_Gateway_EPS',
        'Mollie_WC_Gateway_Giropay',
        'Mollie_WC_Gateway_Ideal',
        'Mollie_WC_Gateway_IngHomePay',
        'Mollie_WC_Gateway_Kbc',
        'Mollie_WC_Gateway_KlarnaPayLater',
        'Mollie_WC_Gateway_KlarnaSliceIt',
        'Mollie_WC_Gateway_Bancontact',
	    // LEGACY - DO NOT REMOVE!
        // MisterCash was renamed to Bancontact, but this class should stay available for old orders and subscriptions!
        'Mollie_WC_Gateway_MisterCash',
        'Mollie_WC_Gateway_PayPal',
        'Mollie_WC_Gateway_Paysafecard',
        'Mollie_WC_Gateway_Sofort',
        'Mollie_WC_Gateway_Giftcard',
    );

    private function __construct () {}

    /**
     *
     */
    public static function schedulePendingPaymentOrdersExpirationCheck()
    {
        if ( class_exists( 'WC_Subscriptions_Order' ) ) {
            $settings_helper = self::getSettingsHelper();
            $time = $settings_helper->getPaymentConfirmationCheckTime();
            $nextScheduledTime = wp_next_scheduled('pending_payment_confirmation_check');
            if (!$nextScheduledTime) {
                wp_schedule_event($time, 'daily', 'pending_payment_confirmation_check');
            }

            add_action('pending_payment_confirmation_check', array(__CLASS__, 'checkPendingPaymentOrdersExpiration'));
        }

    }

    /**
     *
     */
    public static function initDb()
    {
        global $wpdb;
        $wpdb->mollie_pending_payment = $wpdb->prefix . self::PENDING_PAYMENT_DB_TABLE_NAME;
        if(get_option(self::DB_VERSION_PARAM_NAME, '') != self::DB_VERSION){

            global $wpdb;
            $pendingPaymentConfirmTable = $wpdb->prefix . self::PENDING_PAYMENT_DB_TABLE_NAME;
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            if($wpdb->get_var("show tables like '$pendingPaymentConfirmTable'") != $pendingPaymentConfirmTable) {
                $sql = "
					CREATE TABLE " . $pendingPaymentConfirmTable . " (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    post_id bigint NOT NULL,
                    expired_time int NOT NULL,
                    UNIQUE KEY id (id)
                );";
                dbDelta($sql);

	            /**
	             * Remove redundant 'DESCRIBE *__mollie_pending_payment' error so it doesn't show up in error logs
	             */
	            global $EZSQL_ERROR;
				array_pop($EZSQL_ERROR);
            }
            update_option(self::DB_VERSION_PARAM_NAME, self::DB_VERSION);
        }

    }

    /**
     *
     */
    public static function checkPendingPaymentOrdersExpiration()
    {
        global $wpdb;
        $currentDate = new DateTime();
        $items = $wpdb->get_results("SELECT * FROM {$wpdb->mollie_pending_payment} WHERE expired_time < {$currentDate->getTimestamp()};");
        foreach ($items as $item){
	        $order = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $item->post_id );

	        // Check that order actually exists
	        if ( $order == false ) {
		        return false;
	        }

            if ($order->get_status() == Mollie_WC_Gateway_Abstract::STATUS_COMPLETED){

                $new_order_status = Mollie_WC_Gateway_Abstract::STATUS_FAILED;
	            if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		            $paymentMethodId = get_post_meta( $order->id, '_payment_method_title', true );
		            $molliePaymentId = get_post_meta( $order->id, '_mollie_payment_id', true );
	            } else {
		            $paymentMethodId = $order->get_meta( '_payment_method_title', true );
		            $molliePaymentId = $order->get_meta( '_mollie_payment_id', true );
	            }
                $order->add_order_note(sprintf(
                /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                    __('%s payment failed (%s).', 'mollie-payments-for-woocommerce'),
                    $paymentMethodId,$molliePaymentId
                ));

                $order->update_status($new_order_status, '');

	            if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		            if ( get_post_meta( $order->id, '_order_stock_reduced', $single = true ) ) {
			            // Restore order stock
			            Mollie_WC_Plugin::getDataHelper()->restoreOrderStock( $order );

			            Mollie_WC_Plugin::debug( __METHOD__ . " Stock for order {$order->id} restored." );
		            }

		            $wpdb->delete(
			            $wpdb->mollie_pending_payment,
			            array(
				            'post_id' => $order->id,
			            )
		            );
	            } else {
		            if ( $order->get_meta( '_order_stock_reduced', $single = true ) ) {
			            // Restore order stock
			            Mollie_WC_Plugin::getDataHelper()->restoreOrderStock( $order );

			            Mollie_WC_Plugin::debug( __METHOD__ . " Stock for order {$order->get_id()} restored." );
		            }

		            $wpdb->delete(
			            $wpdb->mollie_pending_payment,
			            array(
				            'post_id' => $order->get_id(),
			            )
		            );
	            }
            }
        }

    }

    /**
     * Initialize plugin
     */
	public static function init() {
		if ( self::$initiated ) {
			/*
			 * Already initialized
			 */
			return;
		}

		$plugin_basename = self::getPluginFile();
		$settings_helper = self::getSettingsHelper();
		$data_helper     = self::getDataHelper();

		// Add global Mollie settings to 'WooCommerce -> Checkout -> Checkout Options'
		add_filter( 'woocommerce_payment_gateways_settings', array ( $settings_helper, 'addGlobalSettingsFields' ) );

		// When page 'WooCommerce -> Checkout -> Checkout Options' is saved
		add_action( 'woocommerce_settings_save_checkout', array ( $data_helper, 'deleteTransients' ) );

		// Add Mollie gateways
		add_filter( 'woocommerce_payment_gateways', array ( __CLASS__, 'addGateways' ) );

		// Add settings link to plugins page
		add_filter( 'plugin_action_links_' . $plugin_basename, array ( __CLASS__, 'addPluginActionLinks' ) );

		// Listen to return URL call
		add_action( 'woocommerce_api_mollie_return', array ( __CLASS__, 'onMollieReturn' ) );

		// Show Mollie instructions on order details page
		add_action( 'woocommerce_order_details_after_order_table', array ( __CLASS__, 'onOrderDetails' ), 10, 1 );

		// Disable SEPA as payment option in WooCommerce checkout
		add_filter( 'woocommerce_available_payment_gateways', array ( __CLASS__, 'disableSEPAInCheckout' ), 10, 1 );

		// Disable old MisterCash as payment option in WooCommerce checkout
		add_filter( 'woocommerce_available_payment_gateways', array ( __CLASS__, 'disableMisterCashInCheckout' ), 10, 1 );

		// Disable Mollie methods on some pages
		add_filter( 'woocommerce_available_payment_gateways', array ( __CLASS__, 'disableMollieOnPaymentMethodChange' ), 10, 1 );

		// Set order to paid and processed when eventually completed without Mollie
		add_action( 'woocommerce_payment_complete', array ( __CLASS__, 'setOrderPaidByOtherGateway' ), 10, 1 );

		// Cancel order at Mollie (for Orders API/Klarna)
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'cancelOrderAtMollie' ) );

		// Capture order at Mollie (for Orders API/Klarna)
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'shipAndCaptureOrderAtMollie' ) );

		self::initDb();
		self::schedulePendingPaymentOrdersExpirationCheck();
		// Mark plugin initiated
		self::$initiated = true;
	}

    /**
     * Payment return url callback
     */
    public static function onMollieReturn ()
    {
        $data_helper = self::getDataHelper();

	    $order_id = ! empty( $_GET['order_id'] ) ? sanitize_text_field( $_GET['order_id'] ) : null;
	    $key      = ! empty( $_GET['key'] ) ? sanitize_text_field( $_GET['key'] ) : null;

        $order    = $data_helper->getWcOrder($order_id);

        if (!$order)
        {
            self::setHttpResponseCode(404);
            self::debug(__METHOD__ . ":  Could not find order $order_id.");
            return;
        }

        if (!$order->key_is_valid($key))
        {
            self::setHttpResponseCode(401);
            self::debug(__METHOD__ . ":  Invalid key $key for order $order_id.");
            return;
        }

        $gateway = $data_helper->getWcPaymentGatewayByOrder($order);

        if (!$gateway)
        {
            self::setHttpResponseCode(404);

            self::debug(__METHOD__ . ":  Could not find gateway for order $order_id.");
            return;
        }

        if (!($gateway instanceof Mollie_WC_Gateway_Abstract))
        {
            self::setHttpResponseCode(400);
            self::debug(__METHOD__ . ": Invalid gateway " . get_class($gateway) . " for this plugin. Order $order_id.");
            return;
        }

        /** @var Mollie_WC_Gateway_Abstract $gateway */

        $redirect_url = $gateway->getReturnRedirectUrlForOrder($order);

        // Add utm_nooverride query string
        $redirect_url = add_query_arg(array(
            'utm_nooverride' => 1,
        ), $redirect_url);

        self::debug(__METHOD__ . ": Redirect url on return order " . $gateway->id . ", order $order_id: $redirect_url");

        wp_safe_redirect($redirect_url);
    }

    /**
     * @param WC_Order $order
     */
    public static function onOrderDetails (WC_Order $order)
    {
        if (is_order_received_page())
        {
            /**
             * Do not show instruction again below details on order received page
             * Instructions already displayed on top of order received page by $gateway->thankyou_page()
             *
             * @see Mollie_WC_Gateway_Abstract::thankyou_page
             */
            return;
        }

        $gateway = Mollie_WC_Plugin::getDataHelper()->getWcPaymentGatewayByOrder($order);

        if (!$gateway || !($gateway instanceof Mollie_WC_Gateway_Abstract))
        {
            return;
        }

        /** @var Mollie_WC_Gateway_Abstract $gateway */

        $gateway->displayInstructions($order);
    }

    /**
     * Set HTTP status code
     *
     * @param int $status_code
     */
    public static function setHttpResponseCode ($status_code)
    {
        if (PHP_SAPI !== 'cli' && !headers_sent())
        {
            if (function_exists("http_response_code"))
            {
                http_response_code($status_code);
            }
            else
            {
                header(" ", TRUE, $status_code);
            }
        }
    }

    /**
     * Add Mollie gateways
     *
     * @param array $gateways
     * @return array
     */
	public static function addGateways( array $gateways ) {

		$gateways = array_merge( $gateways, self::$GATEWAYS );

		// Return if function get_current_screen() is not defined
		if ( ! function_exists( 'get_current_screen' ) ) {
			return $gateways;
		}

		// Try getting get_current_screen()
		$current_screen = get_current_screen();

		// Return if get_current_screen() isn't set
		if ( ! $current_screen ) {
			return $gateways;
		}

		// Remove old MisterCash (only) from WooCommerce Payment settings
		if ( is_admin() && ! empty( $current_screen->base ) && $current_screen->base == 'woocommerce_page_wc-settings' ) {
			if ( ( $key = array_search( 'Mollie_WC_Gateway_MisterCash', $gateways ) ) !== false ) {
				unset( $gateways[ $key ] );
			}
		}

		// Remove Klarna if WooCommerce is not version 3.0 or higher
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			if ( is_admin() && ! empty( $current_screen->base ) && $current_screen->base == 'woocommerce_page_wc-settings' ) {
				if ( ( $key = array_search( 'Mollie_WC_Gateway_KlarnaPayLater', $gateways ) ) !== false ) {
					unset( $gateways[ $key ] );
				}
				if ( ( $key = array_search( 'Mollie_WC_Gateway_KlarnaSliceIt', $gateways ) ) !== false ) {
					unset( $gateways[ $key ] );
				}
			}
		}

		return $gateways;
	}

	/**
	 * Add a WooCommerce notification message
	 *
	 * @param string $message Notification message
	 * @param string $type    One of notice, error or success (default notice)
	 *
	 * @return $this
	 */
	public static function addNotice( $message, $type = 'notice' ) {
		$type = in_array( $type, array ( 'notice', 'error', 'success' ) ) ? $type : 'notice';

		// Check for existence of new notification api (WooCommerce >= 2.1)
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $message, $type );
		} else {
			$woocommerce = WooCommerce::instance();

			switch ( $type ) {
				case 'error' :
					$woocommerce->add_error( $message );
					break;
				default :
					$woocommerce->add_message( $message );
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
            $message = ( version_compare( WC_VERSION, '3.0', '<' ) ) ? print_r($message, true) : wc_print_r($message, true);
        }

        // Set debug header
        if ($set_debug_header && PHP_SAPI !== 'cli' && !headers_sent())
        {
            header("X-Mollie-Debug: $message");
        }

	    // Log message
	    if ( self::getSettingsHelper()->isDebugEnabled() ) {

		    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {

			    static $logger;

			    if ( empty( $logger ) ) {
				    $logger = new WC_Logger();
			    }

			    $logger->add( self::PLUGIN_ID . '-' . date( 'Y-m-d' ), $message );

		    } else {

			    $logger = wc_get_logger();

			    $context = array ( 'source' => self::PLUGIN_ID . '-' . date( 'Y-m-d' ) );

			    $logger->debug( $message, $context );

		    }

	    }
    }

    /**
     * Get location of main plugin file
     *
     * @return string
     */
    public static function getPluginFile ()
    {
        return plugin_basename(self::PLUGIN_ID . '/' . self::PLUGIN_ID . '.php');
    }

    /**
     * Get plugin URL
     *
     * @param string $path
     * @return string
     */
    public static function getPluginUrl ($path = '')
    {
    	return M4W_PLUGIN_URL . $path;
    }

    /**
     * Add plugin action links
     * @param array $links
     * @return array
     */
    public static function addPluginActionLinks (array $links)
    {
        $action_links = array(
            // Add link to global Mollie settings
            '<a href="' . self::getSettingsHelper()->getGlobalSettingsUrl() . '">' . __('Mollie settings', 'mollie-payments-for-woocommerce') . '</a>',
        );

        // Add link to log files viewer for WooCommerce >= 2.2.0
        if (version_compare(self::getStatusHelper()->getWooCommerceVersion(), '2.2.0', ">="))
        {
            // Add link to WooCommerce logs
            $action_links[] = '<a href="' . self::getSettingsHelper()->getLogsUrl() . '">' . __('Logs', 'mollie-payments-for-woocommerce') . '</a>';
        }

        return array_merge($action_links, $links);
    }

    /**
     * @return Mollie_WC_Helper_Settings
     */
    public static function getSettingsHelper ()
    {
        static $settings_helper;

        if (!$settings_helper)
        {
            $settings_helper = new Mollie_WC_Helper_Settings();
        }

        return $settings_helper;
    }

    /**
     * @return Mollie_WC_Helper_Api
     */
    public static function getApiHelper ()
    {
        static $api_helper;

        if (!$api_helper)
        {
            $api_helper = new Mollie_WC_Helper_Api(self::getSettingsHelper());
        }

        return $api_helper;
    }

    /**
     * @return Mollie_WC_Helper_Data
     */
    public static function getDataHelper ()
    {
        static $data_helper;

        if (!$data_helper)
        {
            $data_helper = new Mollie_WC_Helper_Data(self::getApiHelper());
        }

        return $data_helper;
    }

    /**
     * @return Mollie_WC_Helper_Status
     */
    public static function getStatusHelper ()
    {
        static $status_helper;

        if (!$status_helper)
        {
            $status_helper = new Mollie_WC_Helper_Status();
        }

        return $status_helper;
    }

	/**
	 * @return Mollie_WC_Helper_PaymentFactory
	 */
	public static function getPaymentFactoryHelper() {
		static $payment_helper;

		if ( ! $payment_helper ) {
			$payment_helper = new Mollie_WC_Helper_PaymentFactory();
		}

		return $payment_helper;

	}

	/**
	 * @return Mollie_WC_Payment_Object
	 */
	public static function getPaymentObject() {
		static $payment_parent;

		if ( ! $payment_parent ) {
			$payment_parent = new Mollie_WC_Payment_Object( null );
		}

		return $payment_parent;

	}

	/**
	 * @return Mollie_WC_Helper_OrderLines
	 */
	public static function getOrderLinesHelper ( $shop_country, WC_Order $order )
	{
		static $order_lines_helper;

		if (!$order_lines_helper)
		{

			$order_lines_helper = new Mollie_WC_Helper_OrderLines( $shop_country, $order );
		}

		return $order_lines_helper;
	}

	/**
	 * Ship all order lines and capture an order at Mollie.
	 *
	 */
	public static function shipAndCaptureOrderAtMollie( $order_id ) {

		// If this is an older WooCommerce version, don't run.
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			return;
		}

		$order = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );

		// Set Klarna payment methods
		$klarna_methods = array (
			'mollie_wc_gateway_klarnasliceit',
			'mollie_wc_gateway_klarnapaylater'
		);

		// TODO David: Check minimal WooCommerce or Mollie status

		// Does WooCommerce order contain a payment via Klarna?
		if ( ! in_array( $order->get_payment_method(), $klarna_methods, true ) ) {
			return;
		}

		Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Try to process completed order for a potential capture at Mollie.' );

		// Does WooCommerce order contain a Mollie Order?
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$mollie_order_id = ( $mollie_order_id = get_post_meta( $order->id, '_mollie_order_id', true ) ) ? $mollie_order_id : false;
		} else {
			$mollie_order_id = ( $mollie_order_id = $order->get_meta( '_mollie_order_id', true ) ) ? $mollie_order_id : false;
		}

		if ( $mollie_order_id == false ) {
			$order->add_order_note( 'Order contains Klarna payment methods, but not a valid Mollie Order ID. Processing capture canceled.' );
			Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Order contains Klarna payment methods, but not a valid Mollie Order ID. Processing capture cancelled.' );

			return;
		}

		// Is test mode enabled?
		$settings_helper = Mollie_WC_Plugin::getSettingsHelper();
		$test_mode       = $settings_helper->isTestModeEnabled();

		try {
			// Get the order from the Mollie API
			$mollie_order = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->orders->get( $mollie_order_id );

			// Check that order is Paid or Authorized and can be captured
			if ( $mollie_order->isCanceled() ) {
				$order->add_order_note( 'Order already canceled at Mollie, can not be shipped/captured.' );
				Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Order already canceled at Mollie, can not be shipped/captured.' );

				return;

			}

			if ( $mollie_order->isCompleted() ) {
				$order->add_order_note( 'Order already completed at Mollie, can not be shipped/captured.' );
				Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Order already completed at Mollie, can not be shipped/captured.' );

				return;

			}

			if ( $mollie_order->isPaid() || $mollie_order->isAuthorized() ) {
				Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->orders->get( $mollie_order_id )->shipAll();
				$order->add_order_note( 'Order successfully updated to shipped at Mollie, capture of funds underway.' );
				Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Order successfully updated to shipped at Mollie, capture of funds underway.' );

				return;

			}

			$order->add_order_note( 'Order not paid or authorized at Mollie yet, can not be shipped.' );
			Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Order not paid or authorized at Mollie yet, can not be shipped.' );

		}
		catch ( Mollie\Api\Exceptions\ApiException $e ) {
			Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Processing shipment & capture failed, error: ' . $e->getMessage() );
		}

		return;
	}


	/**
	 * Cancel an order at Mollie.
	 *
	 */
	public static function cancelOrderAtMollie( $order_id ) {

		// If this is an older WooCommerce version, don't run.
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			return;
		}

		$order = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );

		// Set Klarna payment methods
		$klarna_methods = array (
			'mollie_wc_gateway_klarnasliceit',
			'mollie_wc_gateway_klarnapaylater'
		);

		// TODO David: Check minimal WooCommerce or Mollie status

		// Does WooCommerce order contain a payment via Klarna?
		if ( ! in_array( $order->get_payment_method(), $klarna_methods, true ) ) {
			return;
		}

		Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Try to process cancelled order at Mollie.' );

		// Does WooCommerce order contain a Mollie Order?
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$mollie_order_id = ( $mollie_order_id = get_post_meta( $order->id, '_mollie_order_id', true ) ) ? $mollie_order_id : false;
		} else {
			$mollie_order_id = ( $mollie_order_id = $order->get_meta( '_mollie_order_id', true ) ) ? $mollie_order_id : false;
		}

		if ( $mollie_order_id == false ) {
			$order->add_order_note( 'Order contains Klarna payment methods, but not a valid Mollie Order ID. Canceling order failed.' );
			Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Order contains Klarna payment methods, but not a valid Mollie Order ID. Canceling order failed.' );

			return;
		}

		// Is test mode enabled?
		$settings_helper = Mollie_WC_Plugin::getSettingsHelper();
		$test_mode       = $settings_helper->isTestModeEnabled();

		try {
			// Get the order from the Mollie API
			$mollie_order = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->orders->get( $mollie_order_id );

			// Check that order is not already canceled at Mollie
			if ( $mollie_order->isCanceled() ) {
				$order->add_order_note( 'Order already canceled at Mollie, can not be canceled again.' );
				Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Order already canceled at Mollie, can not be canceled again.' );

				return;

			}

			// Check that order has the correct status to be canceled
			if ( $mollie_order->isCreated() || $mollie_order->isAuthorized() || $mollie_order->isShipping() ) {
				Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->orders->get( $mollie_order_id )->cancel();
				$order->add_order_note( 'Order also cancelled at Mollie.' );
				Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Order cancelled in WooCommerce, also cancelled at Mollie.' );

				return;

			}

			$order->add_order_note( 'Order could not be canceled at Mollie, because order status is ' . $mollie_order->status . '.' );
			Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Order could not be canceled at Mollie, because order status is ' . $mollie_order->status . '.' );

		}
		catch ( Mollie\Api\Exceptions\ApiException $e ) {
			Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Updating order to canceled at Mollie failed, error: ' . $e->getMessage() );
		}

		return;
	}

	/**
	 * Don't show SEPA Direct Debit in WooCommerce Checkout
	 */
	public static function disableSEPAInCheckout( $available_gateways ) {

		if ( is_checkout() ) {
			unset( $available_gateways['mollie_wc_gateway_directdebit'] );
		}

		return $available_gateways;
	}

	/**
	 * Don't show old MisterCash in WooCommerce Checkout
	 */
	public static function disableMisterCashInCheckout( $available_gateways ) {

		if ( is_checkout() ) {
			unset( $available_gateways['mollie_wc_gateway_mistercash'] );
		}

		return $available_gateways;
	}

	/**
	 * Don't show Mollie Payment Methods in WooCommerce Account > Subscriptions
	 */
	public static function disableMollieOnPaymentMethodChange( $available_gateways ) {

		// Can't use $wp->request or is_wc_endpoint_url() to check if this code only runs on /subscriptions and /view-subscriptions,
		// because slugs/endpoints can be translated (with WPML) and other plugins.
		// So disabling on is_account_page (if not checkout, bug in WC) and $_GET['change_payment_method'] for now.

		// Only disable payment methods if WooCommerce Subscriptions is installed
		if ( class_exists( 'WC_Subscription' ) ) {
			// Do not disable if account page is also checkout (workaround for bug in WC), do disable on change payment method page (param)
			if ( ( ! is_checkout() && is_account_page() ) || ! empty( $_GET['change_payment_method'] ) ) {
				foreach ( $available_gateways as $key => $value ) {
					if ( strpos( $key, 'mollie_' ) !== false ) {
						unset( $available_gateways[ $key ] );
					}
				}
			}
		}

		return $available_gateways;
	}

	/**
	 * If an order is paid with another payment method (gateway) after a first payment was
	 * placed with Mollie, set a flag, so status updates (like expired) aren't processed by
	 * Mollie Payments for WooCommerce.
	 */
	public static function setOrderPaidByOtherGateway( $order_id ) {

		$order = wc_get_order( $order_id );

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {

			$mollie_payment_id    = get_post_meta( $order_id, '_mollie_payment_id', $single = true );
			$order_payment_method = get_post_meta( $order_id, '_payment_method', $single = true );

			if ( $mollie_payment_id !== '' && ( strpos( $order_payment_method, 'mollie' ) === false ) ) {
				update_post_meta( $order->id, '_mollie_paid_by_other_gateway', '1' );
			}

		} else {

			$mollie_payment_id    = $order->get_meta( '_mollie_payment_id', $single = true );
			$order_payment_method = $order->get_payment_method();

			if ( $mollie_payment_id !== '' && ( strpos( $order_payment_method, 'mollie' ) === false ) ) {

				$order->update_meta_data( '_mollie_paid_by_other_gateway', '1' );
				$order->save();
			}
		}

		return true;

	}

}

