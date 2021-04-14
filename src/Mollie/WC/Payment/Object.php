<?php

use Mollie\Api\Exceptions\ApiException;

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
	 * @param string $paymentId
	 * @param bool   $testMode (default: false)
	 * @param bool   $useCache (default: true)
	 *
	 * @return Mollie\Api\Resources\Payment|Mollie\Api\Resources\Order|null
	 */
	public function getPaymentObject( $paymentId, $testMode = false, $useCache = true ) {
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
		catch ( ApiException $e ) {
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
		catch ( ApiException $e ) {
			Mollie_WC_Plugin::debug( __FUNCTION__ . ": Could not load order $payment_id (" . ( $test_mode ? 'test' : 'live' ) . "): " . $e->getMessage() . ' (' . get_class( $e ) . ')' );
		}

		return null;
	}

	/**
	 * @param $order
	 * @param $customerId
	 *
	 */
	protected function getPaymentRequestData( $order, $customerId ) {

	}

	/**
	 * Save active Mollie payment id for order
	 *
	 * @param int $orderId
	 *
	 * @return $this
	 */
	public function setActiveMolliePayment( $orderId ) {

		// Do extra checks if WooCommerce Subscriptions is installed
		if ( class_exists( 'WC_Subscriptions' ) && class_exists( 'WC_Subscriptions_Admin' ) ) {
			if ( Mollie_WC_Plugin::getDataHelper()->isWcSubscription($orderId ) ) {
				return $this->setActiveMolliePaymentForSubscriptions($orderId );
			}
		}

		return $this->setActiveMolliePaymentForOrders($orderId );

	}

	/**
	 * Save active Mollie payment id for order
	 *
	 * @param int $order_id
	 *
	 * @return $this
	 */
	public function setActiveMolliePaymentForOrders( $order_id ) {

        static::$order = wc_get_order( $order_id );

        static::$order->update_meta_data( '_mollie_order_id', $this->data->id );
        static::$order->update_meta_data( '_mollie_payment_id', static::$paymentId );
        static::$order->update_meta_data( '_mollie_payment_mode', $this->data->mode );

        static::$order->delete_meta_data( '_mollie_cancelled_payment_id' );

        if ( static::$customerId ) {
            static::$order->update_meta_data( '_mollie_customer_id', static::$customerId );
        }

        static::$order->save();

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

        $order = wc_get_order( $order_id );

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
			if ( Mollie_WC_Plugin::getDataHelper()->isWcSubscription($order_id ) ) {
				return $this->unsetActiveMolliePaymentForSubscriptions( $order_id );
			}
		}

		return $this->unsetActiveMolliePaymentForOrders( $order_id );

	}

    /**
     * Delete active Mollie payment id for order
     *
     * @param int $order_id
     *
     * @return $this
     */
	public function unsetActiveMolliePaymentForOrders( $order_id ) {

        // Only remove Mollie payment details if they belong to this payment, not when a new payment was already placed
        $order             = wc_get_order( $order_id );
        $mollie_payment_id = $order->get_meta( '_mollie_payment_id', true );

        if ( $mollie_payment_id == $this->data->id ) {
            $order->delete_meta_data( '_mollie_payment_id' );
            $order->delete_meta_data( '_mollie_payment_mode' );
            $order->save();
        }

		return $this;
	}

    /**
     * Delete active Mollie payment id for order
     *
     * @param int $order_id
     *
     * @return $this
     */
	public function unsetActiveMolliePaymentForSubscriptions( $order_id ) {

        $order = wc_get_order( $order_id );
        $order->delete_meta_data( '_mollie_payment_id' );
        $order->delete_meta_data( '_mollie_payment_mode' );
        $order->save();

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
        $order             = wc_get_order( $order_id );
        return $order->get_meta('_mollie_payment_id', true );
	}

	/**
	 * Get active Mollie payment id for order
	 *
	 * @param int $order_id
	 *
	 * @return string
	 */
	public function getActiveMollieOrderId( $order_id ) {
        $order             = wc_get_order( $order_id );
        return $order->get_meta('_mollie_order_id', true );
	}

	/**
	 * Get active Mollie payment mode for order
	 *
	 * @param int $order_id
	 *
	 * @return string test or live
	 */
	public function getActiveMolliePaymentMode( $order_id ) {
        $order               = wc_get_order( $order_id );
        return $order->get_meta('_mollie_payment_mode', true );
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
        $order = wc_get_order( $order_id );
        $order->update_meta_data( '_mollie_cancelled_payment_id', $payment_id );
        $order->save();

		return $this;
	}

	/**
	 * @param int $order_id
	 *
	 * @return null
	 */
	public function unsetCancelledMolliePaymentId( $order_id ) {

		// If this order contains a cancelled (previous) payment, remove it.
        $order                       = wc_get_order( $order_id );
        $mollie_cancelled_payment_id = $order->get_meta( '_mollie_cancelled_payment_id', true );

        if ( ! empty( $mollie_cancelled_payment_id ) ) {
            $order = wc_get_order( $order_id );
            $order->delete_meta_data( '_mollie_cancelled_payment_id' );
            $order->save();
        }

		return null;
	}

	/**
	 * @param int $order_id
	 *
	 * @return string|false
	 */
	public function getCancelledMolliePaymentId( $order_id ) {
        $order                       = wc_get_order( $order_id );
        return $order->get_meta('_mollie_cancelled_payment_id', true );
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
	 * @param string                       $paymentMethodTitle
	 */
	public function onWebhookPaid( WC_Order $order, $payment, $paymentMethodTitle ) {

	}

	/**
	 * @param WC_Order                     $order
	 * @param Mollie\Api\Resources\Payment $payment
	 * @param string                       $paymentMethodTitle
	 */
	protected function onWebhookCanceled( WC_Order $order, $payment, $paymentMethodTitle ) {

	}

	/**
	 * @param WC_Order                     $order
	 * @param Mollie\Api\Resources\Payment $payment
	 * @param string                       $paymentMethodTitle
	 */
	protected function onWebhookFailed( WC_Order $order, $payment, $paymentMethodTitle ) {

	}

	/**
	 * @param WC_Order                     $order
	 * @param Mollie\Api\Resources\Payment $payment
	 * @param string                       $paymentMethodTitle
	 */
	protected function onWebhookExpired( WC_Order $order, $payment, $paymentMethodTitle ) {

	}

	/**
	 * Process a payment object refund
	 *
	 * @param object $order
	 * @param int    $orderId
	 * @param object $paymentObject
	 * @param null   $amount
	 * @param string $reason
	 */
	public function refund( WC_Order $order, $orderId, $paymentObject, $amount = null, $reason = '' ) {

	}

	/**
	 * @return bool
	 */
	protected function setOrderPaidAndProcessed( WC_Order $order ) {

        $order->update_meta_data( '_mollie_paid_and_processed', '1' );
        $order->save();

		return true;
	}

	/**
	 * @return bool
	 */
	protected function isOrderPaymentStartedByOtherGateway( WC_Order $order ) {

        $order_id = $order->get_id();
		// Get the current payment method id for the order
		$payment_method_id = get_post_meta( $order_id, '_payment_method', $single = true );

		// If the current payment method id for the order is not Mollie, return true
		if ( ( strpos( $payment_method_id, 'mollie' ) === false ) ) {

			return true;
		}

		return false;

	}
    /**
     * @param WC_Order $order
     */
    public function deleteSubscriptionFromPending(WC_Order $order)
    {
        if (class_exists('WC_Subscriptions')
            && class_exists(
                'WC_Subscriptions_Admin'
            )
        ) {
            if (Mollie_WC_Plugin::getDataHelper()->isSubscription(
                $order->get_id()
            )
            ) {
                $this->deleteSubscriptionOrderFromPendingPaymentQueue($order);
            }
        }
    }

	/**
	 * @param $order
	 */
	public function deleteSubscriptionOrderFromPendingPaymentQueue( $order ) {
		global $wpdb;

        $wpdb->delete(
            $wpdb->mollie_pending_payment,
            array (
                'post_id' => $order->get_id(),
            )
        );
	}

    /**
     * @param WC_Order $order
     *
     * @return bool
     */
    protected function isFinalOrderStatus(WC_Order $order)
    {
        $orderStatus = $order->get_status();
        $isFinalOrderStatus = in_array(
            $orderStatus,
            self::FINAL_STATUSES,
            true
        );

        return $isFinalOrderStatus;
    }
    /**
     * @param                               $orderId
     * @param WC_Payment_Gateway            $gateway
     * @param WC_Order                      $order
     * @param                               $newOrderStatus
     * @param                               $paymentMethodTitle
     * @param \Mollie\Api\Resources\Payment|Mollie\Api\Resources\Order $payment
     */
    protected function failedSubscriptionProcess(
        $orderId,
        WC_Payment_Gateway $gateway,
        WC_Order $order,
        $newOrderStatus,
        $paymentMethodTitle,
        $payment
    ) {
        if (function_exists('wcs_order_contains_renewal')
            && wcs_order_contains_renewal($orderId)
        ) {
            if ($gateway || ($gateway instanceof Mollie_WC_Gateway_Abstract)) {
                $gateway->updateOrderStatus(
                    $order,
                    $newOrderStatus,
                    sprintf(
                    /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                        __(
                            '%s renewal payment failed via Mollie (%s). You will need to manually review the payment and adjust product stocks if you use them.',
                            'mollie-payments-for-woocommerce'
                        ),
                        $paymentMethodTitle,
                        $payment->id . ($payment->mode == 'test' ? (' - ' . __(
                                'test mode',
                                'mollie-payments-for-woocommerce'
                            )) : '')
                    ),
                    $restoreStock = false
                );
            }

            Mollie_WC_Plugin::debug(
                __METHOD__ . ' called for order ' . $orderId . ' and payment '
                . $payment->id . ', renewal order payment failed, order set to '
                . $newOrderStatus . ' for shop-owner review.'
            );

            // Send a "Failed order" email to notify the admin
            $emails = WC()->mailer()->get_emails();
            if (!empty($emails) && !empty($orderId)
                && !empty($emails['WC_Email_Failed_Order'])
            ) {
                $emails['WC_Email_Failed_Order']->trigger($orderId);
            }
        } else {
            if ($gateway || ($gateway instanceof Mollie_WC_Gateway_Abstract)) {
                $gateway->updateOrderStatus(
                    $order,
                    $newOrderStatus,
                    sprintf(
                    /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                        __(
                            '%s payment failed via Mollie (%s).',
                            'mollie-payments-for-woocommerce'
                        ),
                        $paymentMethodTitle,
                        $payment->id . ($payment->mode == 'test' ? (' - ' . __(
                                'test mode',
                                'mollie-payments-for-woocommerce'
                            )) : '')
                    )
                );
            }
        }
    }

    /**
     * @param $orderId
     * @param string $gatewayId
     * @param WC_Order $order
     */
    protected function informNotUpdatingStatus($orderId, $gatewayId, WC_Order $order)
    {
        $orderPaymentMethodTitle = get_post_meta(
            $orderId,
            '_payment_method_title',
            $single = true
        );

        // Add message to log
        Mollie_WC_Plugin::debug(
            $gatewayId . ': Order ' . $order->get_id()
            . ' webhook called, but payment also started via '
            . $orderPaymentMethodTitle . ', so order status not updated.',
            true
        );

        // Add order note
        $order->add_order_note(
            sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                __(
                    'Mollie webhook called, but payment also started via %s, so the order status is not updated.',
                    'mollie-payments-for-woocommerce'
                ),
                $orderPaymentMethodTitle
            )
        );
    }

}
