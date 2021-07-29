<?php

namespace Mollie\WooCommerce;

use DateTime;
use Mollie\Api\CompatibilityChecker;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Refund;
use Mollie\WooCommerce\Buttons\ApplePayButton\ApplePayDirectHandler;
use Mollie\WooCommerce\Buttons\ApplePayButton\DataToAppleButtonScripts;
use Mollie\WooCommerce\Buttons\ApplePayButton\ResponsesToApple;
use Mollie\WooCommerce\Buttons\ApplePayButton\AppleAjaxRequests;
use Mollie\WooCommerce\Buttons\PayPalButton\PayPalAjaxRequests;
use Mollie\WooCommerce\Buttons\PayPalButton\PayPalButtonHandler;
use Mollie\WooCommerce\Buttons\PayPalButton\DataToPayPalScripts;
use Mollie\WooCommerce\Components\AcceptedLocaleValuesDictionary;
use Mollie\WooCommerce\Gateway\AbstractGateway;
use Mollie\WooCommerce\Gateway\ApplePay\Mollie_WC_Gateway_ApplePay;
use Mollie\WooCommerce\Gateway\Voucher\Mollie_WC_Gateway_Voucher;
use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\Payment\OrderItemsRefunder;
use Mollie\WooCommerce\Payment\OrderLines;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Page\MollieSettingsPage;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Utils\Data;
use Mollie\WooCommerce\Utils\GatewaySurchargeHandler;
use Mollie\WooCommerce\Utils\MaybeDisableGateway;
use Mollie\WooCommerce\Utils\MaybeFixSubscription;
use Mollie\WooCommerce\Utils\Status;
use Mollie\WooCommerce\Gateway\BankTransfer\Mollie_WC_Gateway_BankTransfer;
use RuntimeException;
use WC_Order;
use WC_Session;

class Plugin
{
    const PLUGIN_ID      = 'mollie-payments-for-woocommerce';
    const PLUGIN_TITLE   = 'Mollie Payments for WooCommerce';
    const PLUGIN_VERSION = '6.4.0';

    const DB_VERSION     = '1.0';


    /**
     * @var array
     */
    public static $GATEWAY_CLASSNAMES = array(
        'Mollie_WC_Gateway_BankTransfer',
        'Mollie_WC_Gateway_Belfius',
        'Mollie_WC_Gateway_Creditcard',
        'Mollie_WC_Gateway_DirectDebit',
        'Mollie_WC_Gateway_EPS',
        'Mollie_WC_Gateway_Giropay',
        'Mollie_WC_Gateway_Ideal',
        'Mollie_WC_Gateway_Kbc',
        'Mollie_WC_Gateway_KlarnaPayLater',
        'Mollie_WC_Gateway_KlarnaSliceIt',
        'Mollie_WC_Gateway_Bancontact',
        'Mollie_WC_Gateway_PayPal',
        'Mollie_WC_Gateway_Paysafecard',
        'Mollie_WC_Gateway_Przelewy24',
        'Mollie_WC_Gateway_Sofort',
        'Mollie_WC_Gateway_Giftcard',
        'Mollie_WC_Gateway_ApplePay',
        'Mollie_WC_Gateway_MyBank',
        'Mollie_WC_Gateway_Voucher',

    );

    private function __construct () {}

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
            $message = wc_print_r($message, true);
        }

        // Set debug header
        if ($set_debug_header && PHP_SAPI !== 'cli' && !headers_sent())
        {
            header("X-Mollie-Debug: $message");
        }

	    // Log message
	    if ( self::getSettingsHelper()->isDebugEnabled() ) {
            $logger = wc_get_logger();

            $context = array ( 'source' => self::PLUGIN_ID . '-' . date( 'Y-m-d' ) );

            $logger->debug( $message, $context );
	    }
    }


    /**
     * Get plugin URL
     *
     * @param string $path
     * @return string
     */
    public static function getPluginUrl ($path = '')
    {
        return untrailingslashit(M4W_PLUGIN_URL) . '/' . ltrim($path, '/');
    }

    public static function getPluginPath($path = '')
    {
        return untrailingslashit(M4W_PLUGIN_DIR) . '/' . ltrim($path, '/');
    }



    /**
     * @return Settings
     */
    public static function getSettingsHelper ()
    {
        static $settings_helper;

        if (!$settings_helper)
        {
            $settings_helper = new Settings(
                self::PLUGIN_ID,
                self::getStatusHelper(),
                self::PLUGIN_VERSION,
                self::getPluginUrl(),
                [
                    'Mollie\\WooCommerce\\Gateway\\Banktransfer\\Mollie_WC_Gateway_BankTransfer',
                    'Mollie\\WooCommerce\\Gateway\\Belfius\\Mollie_WC_Gateway_Belfius',
                    'Mollie\\WooCommerce\\Gateway\\Creditcard\\Mollie_WC_Gateway_Creditcard',
                    'Mollie\\WooCommerce\\Gateway\\DirectDebit\\Mollie_WC_Gateway_DirectDebit',
                    'Mollie\\WooCommerce\\Gateway\\EPS\\Mollie_WC_Gateway_EPS',
                    'Mollie\\WooCommerce\\Gateway\\Giropay\\Mollie_WC_Gateway_Giropay',
                    'Mollie\\WooCommerce\\Gateway\\Ideal\\Mollie_WC_Gateway_Ideal',
                    'Mollie\\WooCommerce\\Gateway\\Kbc\\Mollie_WC_Gateway_Kbc',
                    'Mollie\\WooCommerce\\Gateway\\KlarnaPayLater\\Mollie_WC_Gateway_KlarnaPayLater',
                    'Mollie\\WooCommerce\\Gateway\\KlarnaSliceIt\\Mollie_WC_Gateway_KlarnaSliceIt',
                    'Mollie\\WooCommerce\\Gateway\\Bancontact\\Mollie_WC_Gateway_Bancontact',
                    'Mollie\\WooCommerce\\Gateway\\PayPal\\Mollie_WC_Gateway_PayPal',
                    'Mollie\\WooCommerce\\Gateway\\Paysafecard\\Mollie_WC_Gateway_Paysafecard',
                    'Mollie\\WooCommerce\\Gateway\\Przelewy24\\Mollie_WC_Gateway_Przelewy24',
                    'Mollie\\WooCommerce\\Gateway\\Sofort\\Mollie_WC_Gateway_Sofort',
                    'Mollie\\WooCommerce\\Gateway\\Giftcard\\Mollie_WC_Gateway_Giftcard',
                    'Mollie\\WooCommerce\\Gateway\\ApplePay\\Mollie_WC_Gateway_ApplePay',
                    'Mollie\\WooCommerce\\Gateway\\MyBank\\Mollie_WC_Gateway_MyBank',
                    'Mollie\\WooCommerce\\Gateway\\Voucher\\Mollie_WC_Gateway_Voucher',
                ]
            );
        }

        return $settings_helper;
    }

    /**
     * @return Api
     */
    public static function getApiHelper ()
    {
        static $api_helper;

        if (!$api_helper)
        {
            $api_helper = new Api(self::getSettingsHelper());
        }

        return $api_helper;
    }

    /**
     * @return Data
     */
    public static function getDataHelper ()
    {
        static $data_helper;

        if (!$data_helper)
        {
            $data_helper = new Data(self::getApiHelper());
        }

        return $data_helper;
    }

    /**
     * @return Status
     */
    public static function getStatusHelper ()
    {
        static $status_helper;

        if (!$status_helper)
        {
            $status_helper = new Status(new CompatibilityChecker());
        }

        return $status_helper;
    }

	/**
	 * @return PaymentFactory
	 */
	public static function getPaymentFactoryHelper() {
		static $payment_helper;

		if ( ! $payment_helper ) {
			$payment_helper = new PaymentFactory();
		}

		return $payment_helper;

	}

	/**
	 * @return MollieObject
	 */
	public static function getPaymentObject() {
		static $payment_parent;

		if ( ! $payment_parent ) {
			$payment_parent = new MollieObject( null );
		}

		return $payment_parent;

	}

	/**
	 * @return OrderLines
	 */
	public static function getOrderLinesHelper ( $shop_country, WC_Order $order )
	{
		static $order_lines_helper;

		if (!$order_lines_helper)
		{

			$order_lines_helper = new OrderLines( $shop_country, $order );
		}

		return $order_lines_helper;
	}

}

