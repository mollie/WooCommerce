<?php

use Mollie\Api\Resources\Refund;

class Mollie_WC_Payment_Payment extends Mollie_WC_Payment_Object {

    const ACTION_AFTER_REFUND_PAYMENT_CREATED = Mollie_WC_Plugin::PLUGIN_ID . '_refund_payment_created';

	public function __construct( $data ) {
		$this->data = $data;
	}

	public function getPaymentObject( $payment_id, $test_mode = false, $use_cache = true ) {
		try {

			// Is test mode enabled?
			$settings_helper = Mollie_WC_Plugin::getSettingsHelper();
			$test_mode       = $settings_helper->isTestModeEnabled();

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
		$payment_description = __( 'Order', 'woocommerce' ) . ' ' . $order->get_order_number();
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

			// Add sequenceType for subscriptions first payments
			if ( class_exists( 'WC_Subscriptions' ) && class_exists( 'WC_Subscriptions_Admin' ) ) {
				if ( Mollie_WC_Plugin::getDataHelper()->isSubscription( $order->id ) ) {

					// See get_available_payment_gateways() in woocommerce-subscriptions/includes/gateways/class-wc-subscriptions-payment-gateways.php
					$disable_automatic_payments = ( 'yes' == get_option( WC_Subscriptions_Admin::$option_prefix . '_turn_off_automatic_payments', 'no' ) ) ? true : false;
					$supports_subscriptions     = $gateway->supports( 'subscriptions' );

					if ( $supports_subscriptions == true && $disable_automatic_payments == false ) {
						$paymentRequestData['sequenceType'] = 'first';
					}
				}
			}

		} else {

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
				'metadata'       => apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_payment_object_metadata', array (
					'order_id'     => $order->get_id(),
				) ),
			);

			// Add sequenceType for subscriptions first payments
			if ( class_exists( 'WC_Subscriptions' ) && class_exists( 'WC_Subscriptions_Admin' ) ) {
				if ( Mollie_WC_Plugin::getDataHelper()->isSubscription( $order->get_id() ) ) {

					// See get_available_payment_gateways() in woocommerce-subscriptions/includes/gateways/class-wc-subscriptions-payment-gateways.php
					$disable_automatic_payments = ( 'yes' == get_option( WC_Subscriptions_Admin::$option_prefix . '_turn_off_automatic_payments', 'no' ) ) ? true : false;
					$supports_subscriptions     = $gateway->supports( 'subscriptions' );

					if ( $supports_subscriptions == true && $disable_automatic_payments == false ) {
						$paymentRequestData['sequenceType'] = 'first';
					}
				}
			}
		}

		if ( $store_customer ) {
			$paymentRequestData['customerId'] = $customer_id;
		}

        $cardToken = mollieWooCommerceCardToken();
        if ($cardToken) {
            $paymentRequestData['cardToken'] = $cardToken;
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

	public function getMollieCustomerIdFromPaymentObject( $payment = null ) {

		if ( $payment == null ) {
			$payment = $this->data->id;
		}

		$payment = $this->getPaymentObject( $payment );

		if ( isset( $payment->customerId ) ) {

			return $payment->customerId;

		}

		return null;
	}

	public function getSequenceTypeFromPaymentObject( $payment = null ) {

		if ( $payment == null ) {
			$payment = $this->data->id;
		}

		$payment = $this->getPaymentObject( $payment );

		if ( isset( $payment->sequenceType ) ) {

			return $payment->sequenceType;

		}

		return null;
	}

	public function getMollieCustomerIbanDetailsFromPaymentObject( $payment = null ) {

		if ( $payment == null ) {
			$payment = $this->data->id;
		}

		$payment = $this->getPaymentObject( $payment );

		$iban_details['consumerName']    = $payment->details->consumerName;
		$iban_details['consumerAccount'] = $payment->details->consumerAccount;

		return $iban_details;

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
			Mollie_WC_Plugin::debug( __METHOD__ . ' called for payment ' . $order_id );

			// WooCommerce 2.2.0 has the option to store the Payment transaction id.
			$woo_version = get_option( 'woocommerce_version', 'Unknown' );

			if ( version_compare( $woo_version, '2.2.0', '>=' ) ) {
				$order->payment_complete( $payment->id );
			} else {
				$order->payment_complete();
			}

			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' WooCommerce payment_complete() processed and returned to ' . __METHOD__ . ' for payment ' . $order_id );

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
			Mollie_WC_Plugin::debug( __METHOD__ . ' processing paid payment via Mollie plugin fully completed for order ' . $order_id );

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
			Mollie_WC_Plugin::debug( __METHOD__ . ' payment at Mollie not paid, so no processing for order ' . $order_id );

		}

	}

	/**
	 * @param WC_Order                     $order
	 * @param Mollie\Api\Resources\Payment $payment
	 * @param string                       $payment_method_title
	 */
	public function onWebhookCanceled( WC_Order $order, $payment, $payment_method_title ) {

		// Get order ID in the correct way depending on WooCommerce version
        $order_id = mollieWooCommerceOrderId($order);

		// Add messages to log
		mollieWooCommerceDebug(__METHOD__ . " called for payment {$order_id}" );

		// if the status is Completed|Refunded|Cancelled  DONT change the status to cancelled
        if ($this->isFinalOrderStatus($order)) {
            mollieWooCommerceDebug(
                __METHOD__
                . " called for payment {$order_id} has final status. Nothing to be done"
            );

            return;
        }

        //status is Pending|Failed|Processing|On-hold so Cancel
		// Get current gateway
		$gateway = Mollie_WC_Plugin::getDataHelper()->getWcPaymentGatewayByOrder( $order );

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
		$new_order_status = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_cancelled_' . $gateway->id, $new_order_status );

		// Update order status, but only if there is no payment started by another gateway
		if ( ! $this->isOrderPaymentStartedByOtherGateway( $order ) ) {

			if ( $gateway || ( $gateway instanceof Mollie_WC_Gateway_Abstract ) ) {
				$gateway->updateOrderStatus( $order, $new_order_status );
			}

		} else {
			$order_payment_method_title = get_post_meta( $order_id, '_payment_method_title', $single = true );

			// Add message to log
			Mollie_WC_Plugin::debug( $gateway->id . ': Order ' . $order->get_id() . ' webhook called, but payment also started via ' . $order_payment_method_title . ', so order status not updated.', true );

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

		// Add messages to log
		Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $order_id );

		// Get current gateway
		$gateway = Mollie_WC_Plugin::getDataHelper()->getWcPaymentGatewayByOrder( $order );

		// New order status
		$new_order_status = Mollie_WC_Gateway_Abstract::STATUS_FAILED;

		// Overwrite plugin-wide
		$new_order_status = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_failed', $new_order_status );

		// Overwrite gateway-wide
		$new_order_status = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_failed_' . $gateway->id, $new_order_status );

		// If WooCommerce Subscriptions is installed, process this failure as a subscription, otherwise as a regular order
		// Update order status for order with failed payment, don't restore stock
		if ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {

			if ( $gateway || ( $gateway instanceof Mollie_WC_Gateway_Abstract ) ) {
				$gateway->updateOrderStatus(
					$order,
					$new_order_status,
					sprintf(
					/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
						__( '%s renewal payment failed via Mollie (%s). You will need to manually review the payment and adjust product stocks if you use them.', 'mollie-payments-for-woocommerce' ),
						$payment_method_title,
						$payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
					),
					$restore_stock = false
				);
			}

			Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $order_id . ' and payment ' . $payment->id . ', renewal order payment failed, order set to ' . $new_order_status . ' for shop-owner review.' );

			// Send a "Failed order" email to notify the admin
			$emails = WC()->mailer()->get_emails();
			if ( ! empty( $emails ) && ! empty( $order_id ) && ! empty( $emails['WC_Email_Failed_Order'] ) ) {
				$emails['WC_Email_Failed_Order']->trigger( $order_id );
			}
		} else {

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
		}

		Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $order_id . ' and payment ' . $payment->id . ', regular payment failed.' );

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
		Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $order_id );

		// Get current gateway
		$gateway = Mollie_WC_Plugin::getDataHelper()->getWcPaymentGatewayByOrder( $order );

		// Check that this payment is the most recent, based on Mollie Payment ID from post meta, do not cancel the order if it isn't
		if ( $mollie_payment_id != $payment->id ) {
			Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $order_id . ' and payment ' . $payment->id . ', not processed because of a newer pending payment ' . $mollie_payment_id );

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
		$new_order_status = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_expired_' . $gateway->id, $new_order_status );

		// Update order status, but only if there is no payment started by another gateway
		if ( ! $this->isOrderPaymentStartedByOtherGateway( $order ) ) {

			if ( $gateway || ( $gateway instanceof Mollie_WC_Gateway_Abstract ) ) {
				$gateway->updateOrderStatus( $order, $new_order_status );
			}

		} else {
			$order_payment_method_title = get_post_meta( $order_id, '_payment_method_title', $single = true );

			// Add message to log
			Mollie_WC_Plugin::debug( $gateway->id . ': Order ' . $order->get_id() . ' webhook called, but payment also started via ' . $order_payment_method_title . ', so order status not updated.', true );

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

	/**
	 * Process a payment object refund
	 *
	 * @param object $order
	 * @param int    $order_id
	 * @param object $payment_object
	 * @param null   $amount
	 * @param string $reason
	 *
	 * @return bool|\WP_Error
	 */
	public function refund( WC_Order $order, $order_id, $payment_object, $amount = null, $reason = '' ) {

		Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Try to process refunds for individual order line(s).' );

		try {

			$payment_object = Mollie_WC_Plugin::getPaymentObject()->getActiveMolliePayment( $order_id );

			if ( ! $payment_object ) {

				$error_message = "Could not find active Mollie payment for WooCommerce order ' . $order_id";

				Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $error_message );

				return new WP_Error( '1', $error_message );
			}

			if ( ! $payment_object->isPaid() ) {

				$error_message = "Can not refund payment $payment_object->id for WooCommerce order $order_id as it is not paid.";

				Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $error_message );

				return new WP_Error( '1', $error_message );
			}

			Mollie_WC_Plugin::debug( __METHOD__ . ' - Create refund - payment object: ' . $payment_object->id . ', WooCommerce order: ' . $order_id . ', amount: ' . Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ) . $amount . ( ! empty( $reason ) ? ', reason: ' . $reason : '' ) );

			do_action( Mollie_WC_Plugin::PLUGIN_ID . '_create_refund', $payment_object, $order );

			// Is test mode enabled?
			$test_mode = Mollie_WC_Plugin::getSettingsHelper()->isTestModeEnabled();

			// Send refund to Mollie
			$refund = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->payments->refund( $payment_object, array (
				'amount'      => array (
					'currency' => Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ),
					'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $amount, Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ) )
				),
				'description' => $reason
			) );

			Mollie_WC_Plugin::debug( __METHOD__ . ' - Refund created - refund: ' . $refund->id . ', payment: ' . $payment_object->id . ', order: ' . $order_id . ', amount: ' . Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ) . $amount . ( ! empty( $reason ) ? ', reason: ' . $reason : '' ) );

            /**
             * After Payment Refund has been created
             *
             * @param Refund $refund
             * @param WC_Order $order
             */
            do_action(self::ACTION_AFTER_REFUND_PAYMENT_CREATED, $refund, $order);

            do_action_deprecated(
                Mollie_WC_Plugin::PLUGIN_ID . '_refund_created',
                [$refund, $order],
                '5.3.1',
                self::ACTION_AFTER_REFUND_PAYMENT_CREATED
            );

			$order->add_order_note( sprintf(
			/* translators: Placeholder 1: currency, placeholder 2: refunded amount, placeholder 3: optional refund reason, placeholder 4: payment ID, placeholder 5: refund ID */
				__( 'Refunded %s%s%s - Payment: %s, Refund: %s', 'mollie-payments-for-woocommerce' ),
				Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ),
				$amount,
				( ! empty( $reason ) ? ' (reason: ' . $reason . ')' : '' ),
				$refund->paymentId,
				$refund->id
			) );


			return true;
		}
		catch ( \Mollie\Api\Exceptions\ApiException $e ) {
			return new WP_Error( 1, $e->getMessage() );
		}
	}

}
