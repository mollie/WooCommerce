<?php
class Mollie_WC_Helper_Data
{
    /**
     * Transient prefix. We can not use plugin slug because this
     * will generate to long keys for the wp_options table.
     *
     * @var string
     */
    const TRANSIENT_PREFIX = 'mollie-wc-';

    /**
     * @var \Mollie\Api\Resources\Method[]|\Mollie\Api\Resources\MethodCollection|array
     */
    protected static $regular_api_methods = array();

    /**
     * @var \Mollie\Api\Resources\Method[]|\Mollie\Api\Resources\MethodCollection|array
     */
    protected static $recurring_api_methods = array();

	/**
	 * @var \Mollie\Api\Resources\MethodCollection[]
	 */
	protected static $method_issuers;

    /**
     * @var Mollie_WC_Helper_Api
     */
    protected $api_helper;

    /**
     * @param Mollie_WC_Helper_Api $api_helper
     */
    public function __construct (Mollie_WC_Helper_Api $api_helper)
    {
        $this->api_helper = $api_helper;
    }

    /**
     * Get current locale
     *
     * @return string
     */
    protected function getCurrentLocale ()
    {
        return apply_filters('wpml_current_language', get_locale());
    }

    /**
     * @param string $transient
     * @return string
     */
    public function getTransientId ($transient)
    {
        global $wp_version;

        /*
         * WordPress will save two options to wp_options table:
         * 1. _transient_<transient_id>
         * 2. _transient_timeout_<transient_id>
         */
        $transient_id       = self::TRANSIENT_PREFIX . $transient;
        $option_name        = '_transient_timeout_' . $transient_id;
        $option_name_length = strlen($option_name);

        $max_option_name_length = 191;

        /**
         * Prior to WordPress version 4.4.0, the maximum length for wp_options.option_name is 64 characters.
         * @see https://core.trac.wordpress.org/changeset/34030
         */
        if ($wp_version < '4.4.0') {
            $max_option_name_length = 64;
        }

        if ($option_name_length > $max_option_name_length)
        {
            trigger_error("Transient id $transient_id is to long. Option name $option_name ($option_name_length) will be to long for database column wp_options.option_name which is varchar($max_option_name_length).", E_USER_WARNING);
        }

        return $transient_id;
    }

    /**
     * Get WooCommerce order
     *
     * @param int $order_id Order ID
     * @return WC_Order|bool
     */
    public function getWcOrder ($order_id)
    {
        if (function_exists('wc_get_order'))
        {
            /**
             * @since WooCommerce 2.2
             */
            return wc_get_order($order_id);
        }

        $order = new WC_Order();

        if ($order->get_order($order_id))
        {
            return $order;
        }

        return false;
    }

    /**
     * @param WC_Order $order
     * @return string
     */
    public function getOrderStatus (WC_Order $order)
    {
        if (method_exists($order, 'get_status'))
        {
            /**
             * @since WooCommerce 2.2
             */
            return $order->get_status();
        }

        return $order->status;
    }

    /**
     * Check if a order has a status
     *
     * @param string|string[] $status
     * @return bool
     */
    public function hasOrderStatus (WC_Order $order, $status)
    {
        if (method_exists($order, 'has_status'))
        {
            /**
             * @since WooCommerce 2.2
             */
            return $order->has_status($status);
        }

        if (!is_array($status))
        {
            $status = array($status);
        }

        return in_array($this->getOrderStatus($order), $status);
    }

    /**
     * Get payment gateway class by order data.
     *
     * @param int|WC_Order $order
     * @return WC_Payment_Gateway|bool
     */
    public function getWcPaymentGatewayByOrder ($order)
    {
        if (function_exists('wc_get_payment_gateway_by_order'))
        {
            /**
             * @since WooCommerce 2.2
             */
            return wc_get_payment_gateway_by_order($order);
        }

        if (WC()->payment_gateways())
        {
            $payment_gateways = WC()
                ->payment_gateways
                ->payment_gateways();
        }
        else
        {
            $payment_gateways = array();
        }

        if (!($order instanceof WC_Order))
        {
            $order = $this->getWcOrder($order);

            if (!$order)
            {
                return false;
            }
        }

	    $order_payment_method = ( version_compare( WC_VERSION, '3.0', '<' ) ) ? $order->payment_method : $order->get_payment_method();

	    return isset($payment_gateways[$order_payment_method]) ? $payment_gateways[$order_payment_method] : false;
    }

    /**
     * Called when page 'WooCommerce -> Checkout -> Checkout Options' is saved
     *
     * @see \Mollie_WC_Plugin::init
     */
    public function deleteTransients ()
    {
        Mollie_WC_Plugin::debug(__METHOD__ . ': Mollie settings saved, delete transients');

        $transient_names = array(
            'api_methods_test',
            'api_methods_live',
            'api_issuers_test',
            'api_issuers_live',
            'ideal_issuers_test',
            'ideal_issuers_live',
	        'kbc_issuers_test',
            'kbc_issuers_live',
	        'giftcard_issuers_test',
            'giftcard_issuers_live',
        );

        $languages   = array_keys(apply_filters('wpml_active_languages', array()));
        $languages[] = $this->getCurrentLocale();

        foreach ($transient_names as $transient_name)
        {
            foreach ($languages as $language)
            {
                delete_transient($this->getTransientId($transient_name . "_$language"));
            }
        }
    }

    /**
     * Get Mollie payment from cache or load from Mollie
     * Skip cache by setting $use_cache to false
     *
     * @param string $payment_id
     * @param bool   $test_mode (default: false)
     * @param bool   $use_cache (default: true)
     * @return Mollie\Api\Resources\Payment|null
     */
    public function getPayment ($payment_id, $test_mode = false, $use_cache = true)
    {
        try
        {

            $payment = $this->api_helper->getApiClient($test_mode)->payments->get($payment_id);

            return $payment;
        }
        catch ( \Mollie\Api\Exceptions\ApiException $e )
        {
            Mollie_WC_Plugin::debug(__FUNCTION__ . ": Could not load payment $payment_id (" . ($test_mode ? 'test' : 'live') . "): " . $e->getMessage() . ' (' . get_class($e) . ')');
        }

        return NULL;
    }


	/**
	 * @param bool|false $test_mode
	 * @param bool|true  $use_cache
	 *
	 * @return array|\Mollie\Api\Resources\MethodCollection
	 */
	public function getAllPaymentMethods( $test_mode = false, $use_cache = true ) {

		$result                  = $this->getRegularPaymentMethods( $test_mode, $use_cache );
		$recurringPaymentMethods = $this->getRecurringPaymentMethods( $test_mode, $use_cache );

		foreach ( $recurringPaymentMethods as $recurringItem ) {
			$notFound = true;
			foreach ( $result as $item ) {
				if ( $item->id == $recurringItem->id ) {
					$notFound = false;
					break;
				}
			}
			if ( $notFound ) {
				$result[] = $recurringItem;
			}
		}

		return $result;
	}

	/**
	 * @param bool $test_mode (default: false)
	 * @param bool $use_cache (default: true)
	 *
	 * @return bool|\Mollie\Api\Resources\MethodCollection
	 */
	public function getRegularPaymentMethods( $test_mode = false, $use_cache = true ) {
		// Already initialized
		if ( $use_cache && ! empty( self::$regular_api_methods ) ) {
			return self::$regular_api_methods;
		}

		self::$regular_api_methods = $this->getApiPaymentMethods( $test_mode, $use_cache );

		return self::$regular_api_methods;
	}


	public function getRecurringPaymentMethods( $test_mode = false, $use_cache = true ) {
		// Already initialized
		if ( $use_cache && ! empty( self::$recurring_api_methods ) ) {
			return self::$recurring_api_methods;
		}

		self::$recurring_api_methods = $this->getApiPaymentMethods( $test_mode, $use_cache, array ( 'sequenceType' => 'recurring' ) );

		return self::$recurring_api_methods;
	}

	protected function getApiPaymentMethods( $test_mode = false, $use_cache = true, $filters = array () ) {
		$methods = array ();

		try {

			$filters_key   = ( ! empty ( $filters['sequenceType'] ) ) ? '_' . $filters['sequenceType'] : '';
			$transient_id = Mollie_WC_Plugin::getDataHelper()->getTransientId( 'api_methods_' . ( $test_mode ? 'test' : 'live' ) . $filters_key );

			if ( $use_cache ) {
				$cached_methods = unserialize( get_transient( $transient_id ) );

				if ( $cached_methods && $cached_methods instanceof \Mollie\Api\Resources\MethodCollection ) {
					return $cached_methods;
				}
			}

			if ( empty ( $methods ) ) {

				// Remove existing expired transients
				delete_transient( $transient_id );

				$methods = $this->api_helper->getApiClient( $test_mode )->methods->all( $filters );

				// Set new transients (as cache)
				set_transient( $transient_id, serialize( $methods ), MINUTE_IN_SECONDS * 5 );

			}

			return $methods;
		}
		catch ( \Mollie\Api\Exceptions\ApiException $e ) {
			Mollie_WC_Plugin::debug( __FUNCTION__ . ": Could not load Mollie methods (" . ( $test_mode ? 'test' : 'live' ) . "): " . $e->getMessage() . ' (' . get_class( $e ) . ')' );
		}

		return $methods;
	}

    /**
     * @param bool   $test_mode (default: false)
     * @param string $method
     * @return \Mollie\Api\Resources\Method|null
     */
    public function getPaymentMethod ($test_mode = false, $method)
    {
        $payment_methods = $this->getAllPaymentMethods($test_mode);

        foreach ($payment_methods as $payment_method)
        {
            if ($payment_method->id == $method)
            {
                return $payment_method;
            }
        }

        return null;
    }

	/**
	 * Get issuers for payment method (e.g. for iDEAL, KBC/CBC payment button, gift cards)
	 *
	 * @param bool        $test_mode (default: false)
	 * @param string|null $method
	 *
	 * @return array|\Mollie\Api\Resources\Method||\Mollie\Api\Resources\MethodCollection
	 */
	public function getMethodIssuers( $test_mode = false, $method = null ) {

		try {

			$transient_id = Mollie_WC_Plugin::getDataHelper()->getTransientId( 'issuers_' . ( $test_mode ? 'test' : 'live' ) );

			$cached_issuers = unserialize( get_transient( $transient_id ) );

			if ( $cached_issuers && $cached_issuers instanceof \Mollie\Api\Resources\MethodCollection ) {
				return $cached_issuers;
			} else {
				$issuers = $this->api_helper->getApiClient( $test_mode )->methods->get( "$method", array ( "include" => "issuers" ) );
				set_transient( $transient_id, serialize( $issuers ), MINUTE_IN_SECONDS * 5 );
			}

			return $issuers;

		}
		catch ( \Mollie\Api\Exceptions\ApiException $e ) {
			Mollie_WC_Plugin::debug( __FUNCTION__ . ": Could not load " . $method . " issuers (" . ( $test_mode ? 'test' : 'live' ) . "): " . $e->getMessage() . ' (' . get_class( $e ) . ')' );
		}

		return array ();
	}

    /**
     * Save active Mollie payment id for order
     *
     * @param int                       $order_id
     * @param object|Mollie\Api\Resources\Payment $payment
     * @return $this
     */
    public function setActiveMolliePayment ($order_id, Mollie\Api\Resources\Payment $payment)
    {
	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    update_post_meta( $order_id, '_mollie_payment_id', $payment->id, $single = true );
		    update_post_meta( $order_id, '_mollie_payment_mode', $payment->mode, $single = true );

		    delete_post_meta( $order_id, '_mollie_cancelled_payment_id' );

		    if ( $payment->customerId ) {
			    update_post_meta( $order_id, '_mollie_customer_id', $payment->customerId, $single = true );
		    }

	    } else {
		    $order = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );

		    $order->update_meta_data( '_mollie_payment_id', $payment->id );
		    $order->update_meta_data( '_mollie_payment_mode', $payment->mode );

		    $order->delete_meta_data( '_mollie_cancelled_payment_id' );

		    if ( $payment->customerId ) {
			    $order->update_meta_data( '_mollie_customer_id', $payment->customerId );
		    }

		    $order->save();
	    }

        return $this;
    }

	/**
	 * @param int         $user_id
	 * @param string|null $customer_id
	 *
	 * @return $this
	 */
	public function setUserMollieCustomerId( $user_id, $customer_id ) {
		if ( ! empty( $customer_id ) ) {
			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				update_user_meta( $user_id, 'mollie_customer_id', $customer_id );
			} else {
				try {
					$customer = new WC_Customer( $user_id );
					$customer->update_meta_data( 'mollie_customer_id', $customer_id );
					$customer->save();
				}
				catch ( Exception $e ) {
					Mollie_WC_Plugin::debug( __FUNCTION__ . ": Couldn't load (and save) WooCommerce customer based on user ID " . $user_id );

				}
			}
		}

		return $this;
	}

    /**
     * @param int  $user_id
     * @param bool $test_mode
     * @return null|string
     */
    public function getUserMollieCustomerId ($user_id, $test_mode = FALSE)
    {
    	// Guest users can't buy subscriptions and don't need a Mollie customer ID
	    // https://github.com/mollie/WooCommerce/issues/132
        if (empty($user_id))
        {
            return NULL;
        }

	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    $customer_id = get_user_meta( $user_id, 'mollie_customer_id', $single = true );
	    } else {
		    $customer    = new WC_Customer( $user_id );
		    $customer_id = $customer->get_meta( 'mollie_customer_id' );

		    // If there is no Mollie Customer ID set, check the most recent active subscription
		    if ( empty( $customer_id ) ) {

			    $customer_latest_subscription = wc_get_orders( array (
				    'limit'    => 1,
				    'customer' => $user_id,
				    'type'     => 'shop_subscription',
				    'status'   => 'wc-active',
			    ) );

			    if ( ! empty( $customer_latest_subscription ) ) {
				    $customer_id = get_post_meta( $customer_latest_subscription[0]->get_id(), '_mollie_customer_id', $single = true );

				    // Store this customer ID as user meta too
				    $this->setUserMollieCustomerId( $user_id, $customer_id );
			    }

		    }
	    }

	    // If there is a Mollie Customer ID set, check that customer ID is valid for this API key
	    if ( ! empty( $customer_id ) ) {

		    try {
			    $this->api_helper->getApiClient( $test_mode )->customers->get( $customer_id );
		    }
		    catch ( \Mollie\Api\Exceptions\ApiException $e ) {
			    Mollie_WC_Plugin::debug( __FUNCTION__ . ": Mollie Customer ID ($customer_id) not valid for user $user_id on this API key, try to create a new one (" . ( $test_mode ? 'test' : 'live' ) . ")." );
			    $customer_id = '';
		    }
	    }

	    // If there is no Mollie Customer ID set, try to create a new Mollie Customer
        if (empty($customer_id))
        {
            try
            {
                $userdata = get_userdata($user_id);

	            // Get the best name for use as Mollie Customer name
	            $user_full_name = $userdata->first_name . ' ' . $userdata->last_name;

	            if ( strlen( trim( $user_full_name ) ) == null ) {
		            $user_full_name = $userdata->display_name;
	            }

	            // Create the Mollie Customer
	            $customer = $this->api_helper->getApiClient( $test_mode )->customers->create( array (
		            'name'     => trim( $user_full_name ),
		            'email'    => trim( $userdata->user_email ),
		            'metadata' => array ( 'user_id' => $user_id ),
	            ) );

                $this->setUserMollieCustomerId($user_id, $customer->id);

                $customer_id = $customer->id;

	            Mollie_WC_Plugin::debug( __FUNCTION__ . ": Created a Mollie Customer ($customer_id) for WordPress user with ID $user_id (" . ( $test_mode ? 'test' : 'live' ) . ")." );

	            return $customer_id;

            }
            catch ( \Mollie\Api\Exceptions\ApiException $e )
            {
	            Mollie_WC_Plugin::debug( __FUNCTION__ . ": Could not create Mollie Customer for WordPress user with ID $user_id (" . ( $test_mode ? 'test' : 'live' ) . "): " . $e->getMessage() . ' (' . get_class( $e ) . ')' );
            }
        } else {
	        Mollie_WC_Plugin::debug( __FUNCTION__ . ": Mollie Customer ID ($customer_id) found and valid for user $user_id on this API key. (" . ( $test_mode ? 'test' : 'live' ) . ")." );
        }

        return $customer_id;
    }

	/**
	 * Delete active Mollie payment id for order
	 *
	 * @param int    $order_id
	 * @param string $payment_id
	 *
	 * @return $this
	 */
	public function unsetActiveMolliePayment( $order_id, $payment_id = NULL ) {

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {

			// Only remove Mollie payment details if they belong to this payment, not when a new payment was already placed
			$mollie_payment_id = get_post_meta( $order_id, '_mollie_payment_id', $single = true );

			if ( $mollie_payment_id == $payment_id ) {
				delete_post_meta( $order_id, '_mollie_payment_id' );
				delete_post_meta( $order_id, '_mollie_payment_mode' );
			}
		} else {

			// Only remove Mollie payment details if they belong to this payment, not when a new payment was already placed
			$order             = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );
			$mollie_payment_id = $order->get_meta( '_mollie_payment_id', true );

			if ( $mollie_payment_id == $payment_id ) {
				$order->delete_meta_data( '_mollie_payment_id' );
				$order->delete_meta_data( '_mollie_payment_mode' );
				$order->save();
			}
		}

		return $this;
	}

    /**
     * Get active Mollie payment id for order
     *
     * @param int $order_id
     * @return string
     */
    public function getActiveMolliePaymentId ($order_id)
    {
	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    $mollie_payment_id = get_post_meta( $order_id, '_mollie_payment_id', $single = true );
	    } else {
		    $order = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );
		    $mollie_payment_id = $order->get_meta( '_mollie_payment_id', true );
	    }

	    return $mollie_payment_id;
    }

    /**
     * Get active Mollie payment mode for order
     *
     * @param int $order_id
     * @return string test or live
     */
    public function getActiveMolliePaymentMode ($order_id)
    {
	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    $mollie_payment_mode = get_post_meta( $order_id, '_mollie_payment_mode', $single = true );
	    } else {
		    $order = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );
		    $mollie_payment_mode = $order->get_meta( '_mollie_payment_mode', true );
	    }

	    return $mollie_payment_mode;
    }

    /**
     * @param int  $order_id
     * @param bool $use_cache
     * @return Mollie\Api\Resources\Payment|null
     */
    public function getActiveMolliePayment ($order_id, $use_cache = true)
    {
        if ($this->hasActiveMolliePayment($order_id))
        {
            return $this->getPayment(
                $this->getActiveMolliePaymentId($order_id),
                $this->getActiveMolliePaymentMode($order_id) == 'test',
                $use_cache
            );
        }

        return null;
    }

    /**
     * Check if the order has an active Mollie payment
     *
     * @param int $order_id
     * @return bool
     */
    public function hasActiveMolliePayment ($order_id)
    {
        $mollie_payment_id = $this->getActiveMolliePaymentId($order_id);

        return !empty($mollie_payment_id);
    }

    /**
     * @param int $order_id
     * @param string $payment_id
     * @return $this
     */
    public function setCancelledMolliePaymentId ($order_id, $payment_id)
    {
	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
	    	add_post_meta($order_id, '_mollie_cancelled_payment_id', $payment_id, $single = true);
	    } else {
		    $order = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );
		    $order->update_meta_data( '_mollie_cancelled_payment_id', $payment_id );
		    $order->save();
	    }

        return $this;
    }

	/**
	 * @param int $order_id
	 *
	 * @return null
	 */
	public function unsetCancelledMolliePaymentId( $order_id ) {

		// If this order contains a cancelled (previous) payment, remove it.
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$mollie_cancelled_payment_id = get_post_meta( $order_id, '_mollie_cancelled_payment_id', $single = true );

			if ( ! empty( $mollie_cancelled_payment_id ) ) {
				delete_post_meta( $order_id, '_mollie_cancelled_payment_id' );
			}
		} else {

			$order                       = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );
			$mollie_cancelled_payment_id = $order->get_meta( '_mollie_cancelled_payment_id', true );

			if ( ! empty( $mollie_cancelled_payment_id ) ) {
				$order = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );
				$order->delete_meta_data( '_mollie_cancelled_payment_id' );
				$order->save();
			}
		}

		return null;
	}

    /**
     * @param int $order_id
     * @return string|false
     */
    public function getCancelledMolliePaymentId ($order_id)
    {
	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    $mollie_cancelled_payment_id = get_post_meta( $order_id, '_mollie_cancelled_payment_id', $single = true );
	    } else {
		    $order = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );
		    $mollie_cancelled_payment_id = $order->get_meta( '_mollie_cancelled_payment_id', true );
	    }

        return $mollie_cancelled_payment_id;
    }

    /**
     * Check if the order has been cancelled
     *
     * @param int $order_id
     * @return bool
     */
    public function hasCancelledMolliePayment ($order_id)
    {
        $cancelled_payment_id = $this->getCancelledMolliePaymentId($order_id);

        return !empty($cancelled_payment_id);
    }

    /**
     * @param WC_Order $order
     */
    public function restoreOrderStock (WC_Order $order)
    {
        foreach ($order->get_items() as $item)
        {
            if ($item['product_id'] > 0)
            {
	            $product = ( version_compare( WC_VERSION, '3.0', '<' ) ) ? $order->get_product_from_item($item) : $item->get_product();

	            if ($product && $product->exists() && $product->managing_stock())
                {
	                $old_stock = ( version_compare( WC_VERSION, '3.0', '<' ) ) ? $product->stock : $product->get_stock_quantity();

                    $qty = apply_filters( 'woocommerce_order_item_quantity', $item['qty'], $order, $item);

	                $new_quantity = ( version_compare( WC_VERSION, '3.0', '<' ) ) ? $product->increase_stock( $qty ) : wc_update_product_stock( $product, $qty, 'increase');

                    do_action('woocommerce_auto_stock_restored', $product, $item);

                    $order->add_order_note(sprintf(
                        __('Item #%s stock incremented from %s to %s.', 'woocommerce'),
                        $item['product_id'],
                        $old_stock,
                        $new_quantity
                    ));

	                if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		                $order->send_stock_notifications( $product, $new_quantity, $item['qty'] );
	                }
                }
            }
        }

        // Mark order stock as not-reduced
	    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
		    delete_post_meta($order->id, '_order_stock_reduced');
	    } else {
		    $order->delete_meta_data( '_order_stock_reduced' );
		    $order->save();
	    }
    }

	/**
	 * Format currency value into Mollie API v2 format
	 *
	 * @param $value
	 *
	 * @return int $value
	 */
	public function formatCurrencyValue( $value, $currency ) {

		// Only the Japanese Yen has no decimals in the currency
		if ( $currency == "JPY" ) {
			$value = number_format( $value, 0, '.', '' );
		} else {
			$value = number_format( $value, 2, '.', '' );
		}

		return $value;
	}

}
