<?php

class Mollie_WC_Payment_Payment extends Mollie_WC_Payment_Object {

	public function __construct( $data ) {
		$this->data = $data;
	}

	public function getPaymentObject( $payment_id, $test_mode = false, $use_cache = true ) {
		try {

			self::$payment = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->payments->get( $payment_id );

			return parent::getPaymentObject( $payment_id, $test_mode = false, $use_cache = true );
		}
		catch ( \Mollie\Api\Exceptions\ApiException $e ) {
			Mollie_WC_Plugin::debug( __FUNCTION__ . ": Could not load payment $payment_id (" . ( $test_mode ? 'test' : 'live' ) . "): " . $e->getMessage() . ' (' . get_class( $e ) . ')' );
		}

		return null;
	}

	/**
	 * @param $order
	 * @param $customer_id
	 *
	 * @return array
	 */
	public function getPaymentRequestData( $order, $customer_id ) {
		$settings_helper     = Mollie_WC_Plugin::getSettingsHelper();
		$payment_description = $settings_helper->getPaymentDescription();
		$payment_locale      = $settings_helper->getPaymentLocale();
		$store_customer      = $settings_helper->shouldStoreCustomer();

		$gateway = Mollie_WC_Plugin::getDataHelper()->getWcPaymentGatewayByOrder( $order );

		if ( ! $gateway || ! ( $gateway instanceof Mollie_WC_Gateway_Abstract ) ) {
			return array ( 'result' => 'failure' );
		}

		$mollie_method   = $gateway->getMollieMethodId();
		$selected_issuer = $gateway->getSelectedIssuer();
		$return_url      = $gateway->getReturnUrl( $order );
		$webhook_url     = $gateway->getWebhookUrl( $order );

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {

			$payment_description = strtr( $payment_description, array (
				'{order_number}' => $order->get_order_number(),
				'{order_date}'   => date_i18n( wc_date_format(), strtotime( $order->order_date ) ),
			) );

			$paymentRequestData = array (
				'amount'      => array (
					'currency' => Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ),
					'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $order->get_total(), Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ) )
				),
				'description' => $payment_description,
				'redirectUrl' => $return_url,
				'webhookUrl'  => $webhook_url,
				'method'      => $mollie_method,
				'issuer'      => $selected_issuer,
				'locale'      => $payment_locale,
				'metadata'    => array (
					'order_id' => $order->id,
				),
			);
		} else {

			$payment_description = strtr( $payment_description, array (
				'{order_number}' => $order->get_order_number(),
				'{order_date}'   => date_i18n( wc_date_format(), $order->get_date_created()->getTimestamp() ),
			) );

			$paymentRequestData = array (
				'amount'      => array (
					'currency' => Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ),
					'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $order->get_total(), Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ) )
				),
				'description' => $payment_description,
				'redirectUrl' => $return_url,
				'webhookUrl'  => $webhook_url,
				'method'      => $mollie_method,
				'issuer'      => $selected_issuer,
				'locale'      => $payment_locale,
				'metadata'    => array (
					'order_id' => $order->get_id(),
				),
			);
		}

		if ( $store_customer ) {
			$paymentRequestData['customerId'] = $customer_id;
		}

		return $paymentRequestData;

	}

	public function setActiveMolliePayment( $order_id ) {

		self::$paymentId  = $this->getMolliePaymentIdFromPaymentObject();
		self::$customerId = $this->getMollieCustomerIdFromPaymentObject();
		self::$order      = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			update_post_meta( self::$order, '_mollie_paid_by_other_gateway', $this->data->id );
		} else {
			self::$order->update_meta_data( '_mollie_payment_id', $this->data->id );
			self::$order->save();
		}

		return parent::setActiveMolliePayment( $order_id );
	}

	public function getMolliePaymentIdFromPaymentObject() {

		if ( isset( $this->data->id ) ) {

			return $this->data->id;

		}

		return null;
	}

	public function getMollieCustomerIdFromPaymentObject() {

		if ( isset( $this->data->customerId ) ) {

			return $this->data->customerId;

		}

		return null;
	}

	/**
	 * @param WC_Order                     $order
	 * @param Mollie\Api\Resources\Payment $payment
	 * @param string                       $payment_method_title
	 */
	public function onWebhookPaid( WC_Order $order, $payment, $payment_method_title ) {

		// Get order ID in the correct way depending on WooCommerce version
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_id = $order->id;
		} else {
			$order_id = $order->get_id();
		}

		if ( $payment->isPaid() ) {

			// Add messages to log
			Mollie_WC_Plugin::debug( __CLASS__ . __METHOD__ . ' called for payment ' . $order_id );

			// WooCommerce 2.2.0 has the option to store the Payment transaction id.
			$woo_version = get_option( 'woocommerce_version', 'Unknown' );

			if ( version_compare( $woo_version, '2.2.0', '>=' ) ) {
				$order->payment_complete( $payment->id );
			} else {
				$order->payment_complete();
			}

			// Add messages to log
			Mollie_WC_Plugin::debug( __CLASS__ . __METHOD__ . ' WooCommerce payment_complete() processed and returned to ' . __METHOD__ . ' for payment ' . $order_id );

			$order->add_order_note( sprintf(
			/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
				__( 'Order completed using %s payment (%s).', 'mollie-payments-for-woocommerce' ),
				$payment_method_title,
				$payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
			) );

			// Mark the order as processed and paid via Mollie
			$this->setOrderPaidAndProcessed( $order );

			// Remove (old) cancelled payments from this order
			$this->unsetCancelledMolliePaymentId( $order_id );

			// Add messages to log
			Mollie_WC_Plugin::debug( __CLASS__ . __METHOD__ . ' processing paid payment via Mollie plugin fully completed for order ' . $order_id );

			// Subscription processing
			if ( class_exists( 'WC_Subscriptions' ) && class_exists( 'WC_Subscriptions_Admin' ) ) {

				if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
					if ( Mollie_WC_Plugin::getDataHelper()->isSubscription( $order->id ) ) {
						$this->deleteSubscriptionOrderFromPendingPaymentQueue( $order );
						WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
					}
				} else {
					if ( Mollie_WC_Plugin::getDataHelper()->isSubscription( $order->get_id() ) ) {
						$this->deleteSubscriptionOrderFromPendingPaymentQueue( $order );
						WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
					}
				}
			}

		} else {

			// Add messages to log
			Mollie_WC_Plugin::debug( __CLASS__ . __METHOD__ . ' payment at Mollie not paid, so no processing for order ' . $order_id );

		}

	}

	/**
	 * @param WC_Order                     $order
	 * @param Mollie\Api\Resources\Payment $payment
	 * @param string                       $payment_method_title
	 */
	public function onWebhookCanceled( WC_Order $order, $payment, $payment_method_title ) {

		// Get order ID in the correct way depending on WooCommerce version
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_id = $order->id;
		} else {
			$order_id = $order->get_id();
		}

		// Add messages to log
		Mollie_WC_Plugin::debug( __CLASS__ . __METHOD__ . ' called for payment ' . $order_id );

		$this->unsetActiveMolliePayment( $order_id, $payment->id );
		$this->setCancelledMolliePaymentId( $order_id, $payment->id );

		// What status does the user want to give orders with cancelled payments?
		$settings_helper                 = Mollie_WC_Plugin::getSettingsHelper();
		$order_status_cancelled_payments = $settings_helper->getOrderStatusCancelledPayments();

		// New order status
		if ( $order_status_cancelled_payments == 'pending' || $order_status_cancelled_payments == null ) {
			$new_order_status = Mollie_WC_Gateway_Abstract::STATUS_PENDING;
		} elseif ( $order_status_cancelled_payments == 'cancelled' ) {
			$new_order_status = Mollie_WC_Gateway_Abstract::STATUS_CANCELLED;
		}

		// Overwrite plugin-wide
		$new_order_status = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_cancelled', $new_order_status );

		// Overwrite gateway-wide
		$new_order_status = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_cancelled_' . $this->id, $new_order_status );

		// Update order status, but only if there is no payment started by another gateway
		if ( ! $this->isOrderPaymentStartedByOtherGateway( $order ) ) {

			$gateway = Mollie_WC_Plugin::getDataHelper()->getWcPaymentGatewayByOrder( $order );

			if ( $gateway || ( $gateway instanceof Mollie_WC_Gateway_Abstract ) ) {
				$gateway->updateOrderStatus( $order, $new_order_status );
			}

		} else {
			$order_payment_method_title = get_post_meta( $order_id, '_payment_method_title', $single = true );

			// Add message to log
			Mollie_WC_Plugin::debug( $this->id . ': Order ' . $order->get_id() . ' webhook called, but payment also started via ' . $order_payment_method_title . ', so order status not updated.', true );

			// Add order note
			$order->add_order_note( sprintf(
			/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
				__( 'Mollie webhook called, but payment also started via %s, so the order status is not updated.', 'mollie-payments-for-woocommerce' ),
				$order_payment_method_title
			) );
		}

		// User cancelled payment on Mollie or issuer page, add a cancel note.. do not cancel order.
		$order->add_order_note( sprintf(
		/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
			__( '%s payment (%s) cancelled .', 'mollie-payments-for-woocommerce' ),
			$payment_method_title,
			$payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
		) );

		// Subscription processing
		if ( class_exists( 'WC_Subscriptions' ) && class_exists( 'WC_Subscriptions_Admin' ) ) {
			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				if ( Mollie_WC_Plugin::getDataHelper()->isSubscription( $order->id ) ) {
					$this->deleteSubscriptionOrderFromPendingPaymentQueue( $order );
				}
			} else {
				if ( Mollie_WC_Plugin::getDataHelper()->isSubscription( $order->get_id() ) ) {
					$this->deleteSubscriptionOrderFromPendingPaymentQueue( $order );
				}
			}
		}

	}

	/**
	 * @param WC_Order                     $order
	 * @param Mollie\Api\Resources\Payment $payment
	 * @param string                       $payment_method_title
	 */
	public function onWebhookFailed( WC_Order $order, $payment, $payment_method_title ) {

		// Get order ID in the correct way depending on WooCommerce version
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_id = $order->id;
		} else {
			$order_id = $order->get_id();
		}

		// New order status
		$new_order_status = Mollie_WC_Gateway_Abstract::STATUS_FAILED;

		// Overwrite plugin-wide
		$new_order_status = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_on_hold', $new_order_status );

		// Overwrite gateway-wide
		$new_order_status = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_on_hold_' . $this->id, $new_order_status );

		// Update order status for order with failed payment, don't restore stock

		$gateway = Mollie_WC_Plugin::getDataHelper()->getWcPaymentGatewayByOrder( $order );

		if ( $gateway || ( $gateway instanceof Mollie_WC_Gateway_Abstract ) ) {

			$gateway->updateOrderStatus(
				$order,
				$new_order_status,
				sprintf(
				/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
					__( '%s payment failed via Mollie (%s).', 'mollie-payments-for-woocommerce' ),
					$payment_method_title,
					$payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
				)
			);
		}

		Mollie_WC_Plugin::debug( __CLASS__ . __METHOD__ . ' called for order ' . $order_id . ' and payment ' . $payment->id . ', regular payment failed.' );

	}

	/**
	 * @param WC_Order                     $order
	 * @param Mollie\Api\Resources\Payment $payment
	 * @param string                       $payment_method_title
	 */
	public function onWebhookExpired( WC_Order $order, $payment, $payment_method_title ) {

		// Get order ID in correct way depending on WooCommerce version
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_id          = $order->id;
			$mollie_payment_id = get_post_meta( $order_id, '_mollie_payment_id', $single = true );
		} else {
			$order_id          = $order->get_id();
			$mollie_payment_id = $order->get_meta( '_mollie_payment_id', true );
		}

		// Add messages to log
		Mollie_WC_Plugin::debug( __CLASS__ . __METHOD__ . ' called for order ' . $order_id );

		// Check that this payment is the most recent, based on Mollie Payment ID from post meta, do not cancel the order if it isn't
		if ( $mollie_payment_id != $payment->id ) {
			Mollie_WC_Plugin::debug( __CLASS__ . __METHOD__ . ' called for order ' . $order_id . ' and payment ' . $payment->id . ', not processed because of a newer pending payment ' . $mollie_payment_id );

			$order->add_order_note( sprintf(
			/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
				__( '%s payment expired (%s) but order not cancelled because of another pending payment (%s).', 'mollie-payments-for-woocommerce' ),
				$payment_method_title,
				$payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' ),
				$mollie_payment_id
			) );

			return;
		}

		// New order status
		$new_order_status = Mollie_WC_Gateway_Abstract::STATUS_CANCELLED;

		// Overwrite plugin-wide
		$new_order_status = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_expired', $new_order_status );

		// Overwrite gateway-wide
		$new_order_status = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_expired_' . $this->id, $new_order_status );

		// Update order status, but only if there is no payment started by another gateway
		if ( ! $this->isOrderPaymentStartedByOtherGateway( $order ) ) {

			$gateway = Mollie_WC_Plugin::getDataHelper()->getWcPaymentGatewayByOrder( $order );

			if ( $gateway || ( $gateway instanceof Mollie_WC_Gateway_Abstract ) ) {
				$gateway->updateOrderStatus( $order, $new_order_status );
			}

		} else {
			$order_payment_method_title = get_post_meta( $order_id, '_payment_method_title', $single = true );

			// Add message to log
			Mollie_WC_Plugin::debug( $this->id . ': Order ' . $order->get_id() . ' webhook called, but payment also started via ' . $order_payment_method_title . ', so order status not updated.', true );

			// Add order note
			$order->add_order_note( sprintf(
			/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
				__( 'Mollie webhook called, but payment also started via %s, so the order status is not updated.', 'mollie-payments-for-woocommerce' ),
				$order_payment_method_title
			) );
		}

		$order->add_order_note( sprintf(
		/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
			__( '%s payment expired (%s).', 'mollie-payments-for-woocommerce' ),
			$payment_method_title,
			$payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
		) );

		// Remove (old) cancelled payments from this order
		$this->unsetCancelledMolliePaymentId( $order_id );

	}

}