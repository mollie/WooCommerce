<?php

class Mollie_WC_Payment_Object {

    const FINAL_STATUSES = ['completed', 'refunded', 'canceled'];

    public static $paymentId;
	public static $customerId;
	public static $order;
	public static $payment;
	public static $shop_country;

	public function __construct( $data ) {
		$this->data = $data;

		$base_location        = wc_get_base_location();
		static::$shop_country = $base_location['country'];

	}


	/**
	 * Get Mollie payment from cache or load from Mollie
	 * Skip cache by setting $use_cache to false
	 *
	 * @param string $payment_id
	 * @param bool   $test_mode (default: false)
	 * @param bool   $use_cache (default: true)
	 *
	 * @return Mollie\Api\Resources\Payment|Mollie\Api\Resources\Order|null
	 */
	public function getPaymentObject( $payment_id, $test_mode = false, $use_cache = true ) {
		return static::$payment;
	}


	/**
	 * Get Mollie payment from cache or load from Mollie
	 * Skip cache by setting $use_cache to false
	 *
	 * @param string $payment_id
	 * @param bool   $test_mode (default: false)
	 * @param bool   $use_cache (default: true)
	 *
	 * @return Mollie\Api\Resources\Payment|null
	 */
	public function getPaymentObjectPayment( $payment_id, $test_mode = false, $use_cache = true ) {
		// TODO David: Duplicate, send to child class.
		try {

			// Is test mode enabled?
			$settings_helper = Mollie_WC_Plugin::getSettingsHelper();
			$test_mode       = $settings_helper->isTestModeEnabled();

			$payment = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->payments->get( $payment_id );

			return $payment;
		}
		catch ( \Mollie\Api\Exceptions\ApiException $e ) {
			Mollie_WC_Plugin::debug( __FUNCTION__ . ": Could not load payment $payment_id (" . ( $test_mode ? 'test' : 'live' ) . "): " . $e->getMessage() . ' (' . get_class( $e ) . ')' );
		}

		return null;
	}

	/**
	 * Get Mollie payment from cache or load from Mollie
	 * Skip cache by setting $use_cache to false
	 *
	 * @param string $payment_id
	 * @param bool   $test_mode (default: false)
	 * @param bool   $use_cache (default: true)
	 *
	 * @return Mollie\Api\Resources\Payment|Mollie\Api\Resources\Order|null
	 */
	public function getPaymentObjectOrder( $payment_id, $test_mode = false, $use_cache = true ) {
		// TODO David: Duplicate, send to child class.
		try {

			// Is test mode enabled?
			$settings_helper = Mollie_WC_Plugin::getSettingsHelper();
			$test_mode       = $settings_helper->isTestModeEnabled();

			$payment = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->orders->get( $payment_id, [ "embed" => "payments" ] );

			return $payment;
		}
		catch ( \Mollie\Api\Exceptions\ApiException $e ) {
			Mollie_WC_Plugin::debug( __FUNCTION__ . ": Could not load order $payment_id (" . ( $test_mode ? 'test' : 'live' ) . "): " . $e->getMessage() . ' (' . get_class( $e ) . ')' );
		}

		return null;
	}

	/**
	 * @param $order
	 * @param $customer_id
	 *
	 */
	protected function getPaymentRequestData( $order, $customer_id ) {

	}

	/**
	 * Save active Mollie payment id for order
	 *
	 * @param int $order_id
	 *
	 * @return $this
	 */
	public function setActiveMolliePayment( $order_id ) {

		// Do extra checks if WooCommerce Subscriptions is installed
		if ( class_exists( 'WC_Subscriptions' ) && class_exists( 'WC_Subscriptions_Admin' ) ) {
			if ( Mollie_WC_Plugin::getDataHelper()->isSubscription( $order_id ) ) {
				return $this->setActiveMolliePaymentForSubscriptions( $order_id );
			}
		}

		return $this->setActiveMolliePaymentForOrders( $order_id );

	}

	/**
	 * Save active Mollie payment id for order
	 *
	 * @param int $order_id
	 *
	 * @return $this
	 */
	public function setActiveMolliePaymentForOrders( $order_id ) {


		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			update_post_meta( $order_id, '_mollie_order_id', $this->data->id, $single = true );
			update_post_meta( $order_id, '_mollie_payment_id', static::$paymentId, $single = true );
			update_post_meta( $order_id, '_mollie_payment_mode', $this->data->mode, $single = true );

			delete_post_meta( $order_id, '_mollie_cancelled_payment_id' );

			if ( static::$customerId ) {
				update_post_meta( $order_id, '_mollie_customer_id', static::$customerId, $single = true );
			}

		} else {

			static::$order = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );

			static::$order->update_meta_data( '_mollie_order_id', $this->data->id );
			static::$order->update_meta_data( '_mollie_payment_id', static::$paymentId );
			static::$order->update_meta_data( '_mollie_payment_mode', $this->data->mode );

			static::$order->delete_meta_data( '_mollie_cancelled_payment_id' );

			if ( static::$customerId ) {
				static::$order->update_meta_data( '_mollie_customer_id', static::$customerId );
			}

			static::$order->save();
		}

		return $this;
	}

	/**
	 * Save active Mollie payment id for order
	 *
	 * @param int $order_id
	 *
	 * @return $this
	 */
	public function setActiveMolliePaymentForSubscriptions( $order_id ) {


		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {

			add_post_meta( $order_id, '_mollie_payment_id', static::$paymentId, $single = true );
			add_post_meta( $order_id, '_mollie_payment_mode', $this->data->mode, $single = true );

			delete_post_meta( $order_id, '_mollie_cancelled_payment_id' );

			if ( static::$customerId ) {
				add_post_meta( $order_id, '_mollie_customer_id', static::$customerId, $single = true );
			}

			// Also store it on the subscriptions being purchased or paid for in the order
			if ( wcs_order_contains_subscription( $order_id ) ) {
				$subscriptions = wcs_get_subscriptions_for_order( $order_id );
			} elseif ( wcs_order_contains_renewal( $order_id ) ) {
				$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
			} else {
				$subscriptions = array ();
			}

			foreach ( $subscriptions as $subscription ) {
				$this->unsetActiveMolliePayment( $subscription->id );
				delete_post_meta( $subscription->id, '_mollie_customer_id' );
				add_post_meta( $subscription->id, '_mollie_payment_id', static::$paymentId, $single = true );
				add_post_meta( $subscription->id, '_mollie_payment_mode', $this->data->mode, $single = true );
				delete_post_meta( $subscription->id, '_mollie_cancelled_payment_id' );
				if ( static::$customerId ) {
					add_post_meta( $subscription->id, '_mollie_customer_id', static::$customerId, $single = true );
				}
			}

		} else {

			$order = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );

			$order->update_meta_data( '_mollie_payment_id', static::$paymentId );
			$order->update_meta_data( '_mollie_payment_mode', $this->data->mode );

			$order->delete_meta_data( '_mollie_cancelled_payment_id' );

			if ( static::$customerId ) {
				$order->update_meta_data( '_mollie_customer_id', static::$customerId );
			}

			// Also store it on the subscriptions being purchased or paid for in the order
			if ( wcs_order_contains_subscription( $order_id ) ) {
				$subscriptions = wcs_get_subscriptions_for_order( $order_id );
			} elseif ( wcs_order_contains_renewal( $order_id ) ) {
				$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
			} else {
				$subscriptions = array ();
			}

			foreach ( $subscriptions as $subscription ) {
				$this->unsetActiveMolliePayment( $subscription->get_id() );
				$subscription->delete_meta_data( '_mollie_customer_id' );
				$subscription->update_meta_data( '_mollie_payment_id', static::$paymentId );
				$subscription->update_meta_data( '_mollie_payment_mode', $this->data->mode );
				$subscription->delete_meta_data( '_mollie_cancelled_payment_id' );
				if ( static::$customerId ) {
					$subscription->update_meta_data( '_mollie_customer_id', static::$customerId );
				}
				$subscription->save();
			}

			$order->save();

		}

		return $this;
	}


	/**
	 * Delete active Mollie payment id for order
	 *
	 * @param int    $order_id
	 * @param string $payment_id
	 *
	 * @return $this
	 */
	public function unsetActiveMolliePayment( $order_id, $payment_id = null ) {

		// Do extra checks if WooCommerce Subscriptions is installed
		if ( class_exists( 'WC_Subscriptions' ) && class_exists( 'WC_Subscriptions_Admin' ) ) {
			if ( Mollie_WC_Plugin::getDataHelper()->isSubscription( $order_id ) ) {
				return $this->unsetActiveMolliePaymentForSubscriptions( $order_id );
			}
		}

		return $this->unsetActiveMolliePaymentForOrders( $order_id );

	}

	/**
	 * Delete active Mollie payment id for order
	 *
	 * @param int    $order_id
	 * @param string $payment_id
	 *
	 * @return $this
	 */
	public function unsetActiveMolliePaymentForOrders( $order_id ) {

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {

			// Only remove Mollie payment details if they belong to this payment, not when a new payment was already placed
			$mollie_payment_id = get_post_meta( $order_id, '_mollie_payment_id', $single = true );

			if ( $mollie_payment_id == $this->data->id ) {
				delete_post_meta( $order_id, '_mollie_payment_id' );
				delete_post_meta( $order_id, '_mollie_payment_mode' );
			}
		} else {

			// Only remove Mollie payment details if they belong to this payment, not when a new payment was already placed
			$order             = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );
			$mollie_payment_id = $order->get_meta( '_mollie_payment_id', true );

			if ( $mollie_payment_id == $this->data->id ) {
				$order->delete_meta_data( '_mollie_payment_id' );
				$order->delete_meta_data( '_mollie_payment_mode' );
				$order->save();
			}
		}

		return $this;
	}

	/**
	 * Delete active Mollie payment id for order
	 *
	 * @param int    $order_id
	 * @param string $payment_id
	 *
	 * @return $this
	 */
	public function unsetActiveMolliePaymentForSubscriptions( $order_id ) {

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			delete_post_meta( $order_id, '_mollie_payment_id' );
			delete_post_meta( $order_id, '_mollie_payment_mode' );
		} else {
			$order = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );
			$order->delete_meta_data( '_mollie_payment_id' );
			$order->delete_meta_data( '_mollie_payment_mode' );
			$order->save();
		}

		return $this;
	}

	/**
	 * Get active Mollie payment id for order
	 *
	 * @param int $order_id
	 *
	 * @return string
	 */
	public function getActiveMolliePaymentId( $order_id ) {
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$mollie_payment_id = get_post_meta( $order_id, '_mollie_payment_id', $single = true );
		} else {
			$order             = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );
			$mollie_payment_id = $order->get_meta( '_mollie_payment_id', true );
		}

		return $mollie_payment_id;
	}

	/**
	 * Get active Mollie payment id for order
	 *
	 * @param int $order_id
	 *
	 * @return string
	 */
	public function getActiveMollieOrderId( $order_id ) {
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$mollie_payment_id = get_post_meta( $order_id, '_mollie_order_id', $single = true );
		} else {
			$order             = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );
			$mollie_payment_id = $order->get_meta( '_mollie_order_id', true );
		}

		return $mollie_payment_id;
	}

	/**
	 * Get active Mollie payment mode for order
	 *
	 * @param int $order_id
	 *
	 * @return string test or live
	 */
	public function getActiveMolliePaymentMode( $order_id ) {
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$mollie_payment_mode = get_post_meta( $order_id, '_mollie_payment_mode', $single = true );
		} else {
			$order               = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );
			$mollie_payment_mode = $order->get_meta( '_mollie_payment_mode', true );
		}

		return $mollie_payment_mode;
	}

	/**
	 * @param int  $order_id
	 * @param bool $use_cache
	 *
	 * @return Mollie\Api\Resources\Payment|null
	 */
	public function getActiveMolliePayment( $order_id, $use_cache = true ) {

		// Check if there is a payment ID stored with order and get it
		if ( $this->hasActiveMolliePayment( $order_id ) ) {
			return $this->getPaymentObjectPayment(
				$this->getActiveMolliePaymentId( $order_id ),
				$this->getActiveMolliePaymentMode( $order_id ) == 'test',
				$use_cache
			);
		}

		// If there is no payment ID, try to get order ID and if it's stored, try getting payment ID from API
		if ( $this->hasActiveMollieOrder( $order_id ) ) {
			$mollie_order = $this->getPaymentObjectOrder($this->getActiveMollieOrderId( $order_id ));

            try {
                $mollie_order = Mollie_WC_Plugin::getPaymentFactoryHelper()->getPaymentObject(
                    $mollie_order
                );
            } catch (ApiException $exception) {
                Mollie_WC_Plugin::debug($exception->getMessage());
                return;
            }

			return $this->getPaymentObjectPayment(
				$mollie_order->getMolliePaymentIdFromPaymentObject(),
				$this->getActiveMolliePaymentMode( $order_id ) == 'test',
				$use_cache
			);
		}

		return null;
	}

	/**
	 * Check if the order has an active Mollie payment
	 *
	 * @param int $order_id
	 *
	 * @return bool
	 */
	public function hasActiveMolliePayment( $order_id ) {
		$mollie_payment_id = $this->getActiveMolliePaymentId( $order_id );

		return ! empty( $mollie_payment_id );
	}

	/**
	 * Check if the order has an active Mollie order
	 *
	 * @param int $order_id
	 *
	 * @return bool
	 */
	public function hasActiveMollieOrder( $order_id ) {
		$mollie_payment_id = $this->getActiveMollieOrderId( $order_id );

		return ! empty( $mollie_payment_id );
	}

	/**
	 * @param int    $order_id
	 * @param string $payment_id
	 *
	 * @return $this
	 */
	public function setCancelledMolliePaymentId( $order_id, $payment_id ) {
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			add_post_meta( $order_id, '_mollie_cancelled_payment_id', $payment_id, $single = true );
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
	 *
	 * @return string|false
	 */
	public function getCancelledMolliePaymentId( $order_id ) {
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$mollie_cancelled_payment_id = get_post_meta( $order_id, '_mollie_cancelled_payment_id', $single = true );
		} else {
			$order                       = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );
			$mollie_cancelled_payment_id = $order->get_meta( '_mollie_cancelled_payment_id', true );
		}

		return $mollie_cancelled_payment_id;
	}

	/**
	 * Check if the order has been cancelled
	 *
	 * @param int $order_id
	 *
	 * @return bool
	 */
	public function hasCancelledMolliePayment( $order_id ) {
		$cancelled_payment_id = $this->getCancelledMolliePaymentId( $order_id );

		return ! empty( $cancelled_payment_id );
	}


	public function getMolliePaymentIdFromPaymentObject() {


	}

	public function getMollieCustomerIdFromPaymentObject() {


	}

	/**
	 * @param WC_Order                     $order
	 * @param Mollie\Api\Resources\Payment $payment
	 * @param string                       $payment_method_title
	 */
	public function onWebhookPaid( WC_Order $order, $payment, $payment_method_title ) {

	}

	/**
	 * @param WC_Order                     $order
	 * @param Mollie\Api\Resources\Payment $payment
	 * @param string                       $payment_method_title
	 */
	protected function onWebhookCanceled( WC_Order $order, $payment, $payment_method_title ) {

	}

	/**
	 * @param WC_Order                     $order
	 * @param Mollie\Api\Resources\Payment $payment
	 * @param string                       $payment_method_title
	 */
	protected function onWebhookFailed( WC_Order $order, $payment, $payment_method_title ) {

	}

	/**
	 * @param WC_Order                     $order
	 * @param Mollie\Api\Resources\Payment $payment
	 * @param string                       $payment_method_title
	 */
	protected function onWebhookExpired( WC_Order $order, $payment, $payment_method_title ) {

	}

	/**
	 * Process a payment object refund
	 *
	 * @param object $order
	 * @param int    $order_id
	 * @param object $payment_object
	 * @param null   $amount
	 * @param string $reason
	 */
	public function refund( WC_Order $order, $order_id, $payment_object, $amount = null, $reason = '' ) {

	}

	/**
	 * @return bool
	 */
	protected function setOrderPaidAndProcessed( WC_Order $order ) {

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_id = $order->id;
			update_post_meta( $order_id, '_mollie_paid_and_processed', '1' );
		} else {
			$order->update_meta_data( '_mollie_paid_and_processed', '1' );
			$order->save();
		}

		return true;
	}

	/**
	 * @return bool
	 */
	protected function isOrderPaymentStartedByOtherGateway( WC_Order $order ) {

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_id = $order->id;
		} else {
			$order_id = $order->get_id();
		}

		// Get the current payment method id for the order
		$payment_method_id = get_post_meta( $order_id, '_payment_method', $single = true );

		// If the current payment method id for the order is not Mollie, return true
		if ( ( strpos( $payment_method_id, 'mollie' ) === false ) ) {

			return true;
		}

		return false;

	}

	/**
	 * @param $order
	 */
	public function deleteSubscriptionOrderFromPendingPaymentQueue( $order ) {
		global $wpdb;

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$wpdb->delete(
				$wpdb->mollie_pending_payment,
				array (
					'post_id' => $order->id,
				)
			);

		} else {
			$wpdb->delete(
				$wpdb->mollie_pending_payment,
				array (
					'post_id' => $order->get_id(),
				)
			);

		}
	}

    /**
     * @param WC_Order $order
     *
     * @return bool
     */
    protected function isFinalOrderStatus(WC_Order $order)
    {
        $dataHelper = mollieWooCommerceGetDataHelper();
        $orderStatus = $dataHelper->getOrderStatus($order);
        $isFinalOrderStatus = in_array(
            $orderStatus,
            self::FINAL_STATUSES,
            true
        );

        return $isFinalOrderStatus;
    }

}
