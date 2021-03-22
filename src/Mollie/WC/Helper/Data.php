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
            return $this->api_helper->getApiClient($test_mode)->payments->get($payment_id);
        }
        catch ( \Mollie\Api\Exceptions\ApiException $e )
        {
            Mollie_WC_Plugin::debug(__FUNCTION__ . ": Could not load payment $payment_id (" . ($test_mode ? 'test' : 'live') . "): " . $e->getMessage() . ' (' . get_class($e) . ')');
        }

        return NULL;
    }


	/**
	 * @param bool $test_mode
	 * @param bool $use_cache
	 *
	 * @return array|mixed|\Mollie\Api\Resources\Method[]|\Mollie\Api\Resources\MethodCollection
	 */
	public function getAllPaymentMethods( $test_mode = false, $use_cache = true ) {

		$result                  = $this->getRegularPaymentMethods( $test_mode, $use_cache );
		$recurringPaymentMethods = $this->getRecurringPaymentMethods( $test_mode, $use_cache );

        if(!is_array($result)){
            $result = unserialize($result);
        }
        if(!is_array($recurringPaymentMethods)){
            $recurringPaymentMethods = unserialize($recurringPaymentMethods);
        }

		foreach ( $recurringPaymentMethods as $recurringItem ) {
			$notFound = true;
			foreach ( $result as $item ) {
				if ( $item['id'] == $recurringItem['id'] ) {
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
	 * @param bool $test_mode
	 * @param bool $use_cache
	 *
	 * @return array|mixed|\Mollie\Api\Resources\Method[]|\Mollie\Api\Resources\MethodCollection
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

	public function getApiPaymentMethods( $test_mode = false, $use_cache = true, $filters = array () ) {

        $methods = [];

		$filters_key = $filters;
		$filters_key['mode'] = ( $test_mode ? 'test' : 'live' );
		$filters_key['api'] = 'methods';

		try {

			$transient_id = Mollie_WC_Plugin::getDataHelper()->getTransientId( md5(http_build_query($filters_key)));

			if ($use_cache) {
				// When no cache exists $methods will be `false`
				$methods =  get_transient( $transient_id );
			}

			// No cache exists, call the API and cache the result
			if ( !$methods ) {

				$filters['resource'] = 'orders';
				$filters['includeWallets'] = 'applepay';

				$methods = $this->api_helper->getApiClient( $test_mode )->methods->all( $filters );

				$methods_cleaned = array();

				foreach ( $methods as $method ) {
					$public_properties = get_object_vars( $method ); // get only the public properties of the object
					$methods_cleaned[] = $public_properties;
				}

				// $methods_cleaned is empty array when the API doesn't return any methods, cache the empty array
				$methods = $methods_cleaned;

				// Set new transients (as cache)
                if($use_cache){
                    set_transient( $transient_id, $methods, HOUR_IN_SECONDS );
                }
			}

			return $methods;
		}
		catch ( \Mollie\Api\Exceptions\ApiException $e ) {
			Mollie_WC_Plugin::debug( __FUNCTION__ . ": Could not load Mollie methods (" . ( $test_mode ? 'test' : 'live' ) . "): " . $e->getMessage() . ' (' . get_class( $e ) . ')' );

			return array();
		}
	}

	/**
	 * @param bool $test_mode
	 * @param      $method
	 *
	 * @return mixed|\Mollie\Api\Resources\Method|null
	 */
    public function getPaymentMethod ($method, $test_mode = false)
    {
        $payment_methods = $this->getAllPaymentMethods($test_mode);

        foreach ($payment_methods as $payment_method)
        {
            if ($payment_method['id'] == $method)
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

			$transient_id = Mollie_WC_Plugin::getDataHelper()->getTransientId( $method . '_issuers_' . ( $test_mode ? 'test' : 'live' ) );

			// When no cache exists $cached_issuers will be `false`
			$issuers = get_transient( $transient_id );

			if ( !$issuers || !is_array($issuers) ) {

				$method  = $this->api_helper->getApiClient( $test_mode )->methods->get( "$method", array ( "include" => "issuers" ) );
				$issuers = $method->issuers;

				// Set new transients (as cache)
                set_transient( $transient_id, $issuers, HOUR_IN_SECONDS );
			}

			return $issuers;
		}
		catch ( \Mollie\Api\Exceptions\ApiException $e ) {
			Mollie_WC_Plugin::debug( __FUNCTION__ . ": Could not load " . $method . " issuers (" . ( $test_mode ? 'test' : 'live' ) . "): " . $e->getMessage() . ' (' . get_class( $e ) . ')' );
		}

		return array ();
	}

	/**
	 * @param int         $user_id
	 * @param string|null $customer_id
	 *
	 * @return $this
	 */
	public function setUserMollieCustomerId( $user_id, $customer_id ) {
		if ( ! empty( $customer_id ) ) {
            try {
                $customer = new WC_Customer( $user_id );
                $customer->update_meta_data( 'mollie_customer_id', $customer_id );
                $customer->save();
                Mollie_WC_Plugin::debug( __FUNCTION__ . ": Stored Mollie customer ID " . $customer_id . " with user " . $user_id );
            }
            catch ( Exception $e ) {
                Mollie_WC_Plugin::debug( __FUNCTION__ . ": Couldn't load (and save) WooCommerce customer based on user ID " . $user_id );

            }
		}

		return $this;
	}

	/**
	 * @param $orderId
	 * @param $customer_id
	 * @return $this
	 */
	public function setUserMollieCustomerIdAtSubscription ($orderId, $customer_id)
	{
		if (!empty($customer_id))
		{
            $order = wc_get_order( $orderId );
            $order->update_meta_data( '_mollie_customer_id', $customer_id );
            $order->save();
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
     * Get active Mollie payment mode for order
     *
     * @param int $order_id
     * @return string test or live
     */
    public function getActiveMolliePaymentMode ($order_id)
    {
        $order = wc_get_order( $order_id );
        $mollie_payment_mode = $order->get_meta( '_mollie_payment_mode', true );

	    return $mollie_payment_mode;
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
	            $product = $item->get_product();

	            if ($product && $product->exists() && $product->managing_stock())
                {
	                $old_stock =$product->get_stock_quantity();

                    $qty = apply_filters( 'woocommerce_order_item_quantity', $item['qty'], $order, $item);

	                $new_quantity =  wc_update_product_stock( $product, $qty, 'increase');

                    do_action('woocommerce_auto_stock_restored', $product, $item);

                    $order->add_order_note(sprintf(
                        __('Item #%s stock incremented from %s to %s.', 'woocommerce'),
                        $item['product_id'],
                        $old_stock,
                        $new_quantity
                    ));
                }
            }
        }

        // Mark order stock as not-reduced
        $order->delete_meta_data( '_order_stock_reduced' );
        $order->save();
    }


	/**
	 * Get the WooCommerce currency for current order
	 *
	 * @param $order
	 *
	 * @return string $value
	 */
	public function getOrderCurrency( WC_Order $order ) {
        return $order->get_currency();
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

	/**
	 * @param $order_id
	 *
	 * @return bool
	 */
	public function isWcSubscription( $order_id ) {
		return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || function_exists( 'wcs_is_subscription' ) && wcs_is_subscription( $order_id ) || function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) );
	}

    public function isSubscription($orderId)
    {
        $isSubscription = false;
        $isSubscription = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_is_subscription_payment', $isSubscription, $orderId );
        return $isSubscription;
    }
}
