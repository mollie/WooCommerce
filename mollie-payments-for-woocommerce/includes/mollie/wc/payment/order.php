<?php

class Mollie_WC_Payment_Order extends Mollie_WC_Payment_Object {

	public static $paymentId;
	public static $customerId;
	public static $order;
	public static $payment;
	public static $shop_country;

	public function __construct( $data ) {
		$this->data = $data;
	}

	public function getPaymentObject( $payment_id, $test_mode = false, $use_cache = true ) {
		try {

			// Is test mode enabled?
			$settings_helper = Mollie_WC_Plugin::getSettingsHelper();
			$test_mode       = $settings_helper->isTestModeEnabled();

			self::$payment = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->orders->get( $payment_id, [ "embed" => "payments" ] );

			return parent::getPaymentObject( $payment_id, $test_mode = false, $use_cache = true );
		}
		catch ( \Mollie\Api\Exceptions\ApiException $e ) {
			Mollie_WC_Plugin::debug( __CLASS__ . __FUNCTION__ . ": Could not load payment $payment_id (" . ( $test_mode ? 'test' : 'live' ) . "): " . $e->getMessage() . ' (' . get_class( $e ) . ')' );
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
						$paymentRequestData['payment']['sequenceType'] = 'first';
					}
				}
			}

		} else {

			// Setup billing and shipping objects
			$billingAddress  = new stdClass();
			$shippingAddress = new stdClass();

			// Get user details
			$billingAddress->givenName  = ( ctype_space( $order->get_billing_first_name() ) ) ? null : $order->get_billing_first_name();
			$billingAddress->familyName = ( ctype_space( $order->get_billing_last_name() ) ) ? null : $order->get_billing_last_name();
			$billingAddress->email      = ( ctype_space( $order->get_billing_email() ) ) ? null : $order->get_billing_email();

			// Get user details
			$shippingAddress->givenName  = ( ctype_space( $order->get_shipping_first_name() ) ) ? null : $order->get_shipping_first_name();
			$shippingAddress->familyName = ( ctype_space( $order->get_shipping_last_name() ) ) ? null : $order->get_shipping_last_name();
			$shippingAddress->email      = ( ctype_space( $order->get_billing_email() ) ) ? null : $order->get_billing_email(); // WooCommerce doesn't have a shipping email

			// Create billingAddress object
			$billingAddress->streetAndNumber  = ( ctype_space( $order->get_billing_address_1() ) ) ? null : $order->get_billing_address_1();
			$billingAddress->streetAdditional = ( ctype_space( $order->get_billing_address_2() ) ) ? null : $order->get_billing_address_2();
			$billingAddress->postalCode       = ( ctype_space( $order->get_billing_postcode() ) ) ? null : $order->get_billing_postcode();
			$billingAddress->city             = ( ctype_space( $order->get_billing_city() ) ) ? null : $order->get_billing_city();
			$billingAddress->region           = ( ctype_space( $order->get_billing_state() ) ) ? null : $order->get_billing_state();
			$billingAddress->country          = ( ctype_space( $order->get_billing_country() ) ) ? null : $order->get_billing_country();

			// Create shippingAddress object
			$shippingAddress->streetAndNumber  = ( ctype_space( $order->get_shipping_address_1() ) ) ? null : $order->get_shipping_address_1();
			$shippingAddress->streetAdditional = ( ctype_space( $order->get_shipping_address_2() ) ) ? null : $order->get_shipping_address_2();
			$shippingAddress->postalCode       = ( ctype_space( $order->get_shipping_postcode() ) ) ? null : $order->get_shipping_postcode();
			$shippingAddress->city             = ( ctype_space( $order->get_shipping_city() ) ) ? null : $order->get_shipping_city();
			$shippingAddress->region           = ( ctype_space( $order->get_shipping_state() ) ) ? null : $order->get_shipping_state();
			$shippingAddress->country          = ( ctype_space( $order->get_shipping_country() ) ) ? null : $order->get_shipping_country();

			// Generate order lines for Mollie Orders
			$order_lines_helper = Mollie_WC_Plugin::getOrderLinesHelper( self::$shop_country, $order );
			$order_lines        = $order_lines_helper->order_lines();

			// Build the Mollie order data
			$paymentRequestData = array (
				'amount'          => array (
					'currency' => Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ),
					'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $order->get_total(), Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ) )
				),
				'redirectUrl'     => $return_url,
				'webhookUrl'      => $webhook_url,
				'method'          => $mollie_method,
				'payment'         => array (
					'issuer' => $selected_issuer
				),
				'locale'          => $payment_locale,
				'billingAddress'  => $billingAddress,
				'metadata'        => array (
					'order_id'     => $order->get_id(),
					'order_number' => $order->get_order_number(),
				),
				'lines'           => $order_lines['lines'],
				'orderNumber'     => $order->get_order_number(), // TODO David: use order number or order id?
			);

			// Add sequenceType for subscriptions first payments
			if ( class_exists( 'WC_Subscriptions' ) && class_exists( 'WC_Subscriptions_Admin' ) ) {
				if ( Mollie_WC_Plugin::getDataHelper()->isSubscription( $order->get_id() ) ) {

					// See get_available_payment_gateways() in woocommerce-subscriptions/includes/gateways/class-wc-subscriptions-payment-gateways.php
					$disable_automatic_payments = ( 'yes' == get_option( WC_Subscriptions_Admin::$option_prefix . '_turn_off_automatic_payments', 'no' ) ) ? true : false;
					$supports_subscriptions     = $gateway->supports( 'subscriptions' );

					if ( $supports_subscriptions == true && $disable_automatic_payments == false ) {
						$paymentRequestData['payment']['sequenceType'] = 'first';
					}
				}
			}
		}

		// Only add shippingAddress if all required fields are set
		if ( ! empty( $shippingAddress->streetAndNumber ) && ! empty( $shippingAddress->postalCode ) && ! empty( $shippingAddress->city ) && ! empty( $shippingAddress->country ) ) {
			$paymentRequestData['shippingAddress'] = $shippingAddress;
		}

		// Only store customer at Mollie if setting is enabled
		if ( $store_customer ) {
			$paymentRequestData['payment']['customerId'] = $customer_id;
		}

		return $paymentRequestData;

	}

	public function setActiveMolliePayment( $order_id ) {

		self::$paymentId  = $this->getMolliePaymentIdFromPaymentObject();
		self::$customerId = $this->getMollieCustomerIdFromPaymentObject();

		self::$order = Mollie_WC_Plugin::getDataHelper()->getWcOrder( $order_id );
		self::$order->update_meta_data( '_mollie_order_id', $this->data->id );
		self::$order->save();

		return parent::setActiveMolliePayment( $order_id );
	}

	public function getMolliePaymentIdFromPaymentObject() {

		// TODO David: Quick fix, make sure payment object has payments embedded, there needs to be a better way to do this!
		$payment = $this->getPaymentObject($this->data->id);

		if ( isset( $payment->_embedded->payments{0}->id ) ) {

			return $payment->_embedded->payments{0}->id;

		}

		return null;
	}

	public function getMollieCustomerIdFromPaymentObject( $payment = null ) {

		// TODO David: Quick fix, make sure payment object has payments embedded, there needs to be a better way to do this!
		if ( $payment == null ) {
			$payment = $this->data->id;
		}

		$payment = $this->getPaymentObject( $payment );

		if ( isset( $payment->_embedded->payments{0}->customerId ) ) {

			return $payment->_embedded->payments{0}->customerId;

		}

		return null;
	}

	public function getSequenceTypeFromPaymentObject( $payment = null ) {

		// TODO David: Quick fix, make sure payment object has payments embedded, there needs to be a better way to do this!
		if ( $payment == null ) {
			$payment = $this->data->id;
		}

		$payment = $this->getPaymentObject( $payment );

		if ( isset( $payment->_embedded->payments{0}->sequenceType ) ) {

			return $payment->_embedded->payments{0}->sequenceType;

		}

		return null;
	}

	public function getMollieCustomerIbanDetailsFromPaymentObject( $payment = null ) {

		// TODO David: Quick fix, make sure payment object has payments embedded, there needs to be a better way to do this!
		if ( $payment == null ) {
			$payment = $this->data->id;
		}

		$payment = $this->getPaymentObject( $payment );

		if ( isset( $payment->_embedded->payments{0}->id ) ) {

			$actual_payment = new Mollie_WC_Payment_Payment( $payment->_embedded->payments{0}->id );
			$actual_payment = $actual_payment->getPaymentObject( $actual_payment->data );

			$iban_details['consumerName']    = $actual_payment->details->consumerName;
			$iban_details['consumerAccount'] = $actual_payment->details->consumerAccount;

		}

		return $iban_details;

	}

	/**
	 * @param WC_Order                   $order
	 * @param Mollie\Api\Resources\Order $payment
	 * @param string                     $payment_method_title
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
			Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $order_id );

			// WooCommerce 2.2.0 has the option to store the Payment transaction id.
			$woo_version = get_option( 'woocommerce_version', 'Unknown' );

			if ( version_compare( $woo_version, '2.2.0', '>=' ) ) {
				$order->payment_complete( $payment->id );
			} else {
				$order->payment_complete();
			}

			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' WooCommerce payment_complete() processed and returned to ' . __METHOD__ . ' for order ' . $order_id );

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
			Mollie_WC_Plugin::debug( __METHOD__ . ' processing paid order via Mollie plugin fully completed for order ' . $order_id );

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
	 * @param WC_Order                   $order
	 * @param Mollie\Api\Resources\Order $payment
	 * @param string                     $payment_method_title
	 */
	public function onWebhookAuthorized( WC_Order $order, $payment, $payment_method_title ) {

		// Get order ID in the correct way depending on WooCommerce version
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_id = $order->id;
		} else {
			$order_id = $order->get_id();
		}

		if ( $payment->isAuthorized() ) {

			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $order_id );

			// WooCommerce 2.2.0 has the option to store the Payment transaction id.
			$woo_version = get_option( 'woocommerce_version', 'Unknown' );

			// TODO David: Keep WooCommerce payment_complete() here?
			if ( version_compare( $woo_version, '2.2.0', '>=' ) ) {
				$order->payment_complete( $payment->id );
			} else {
				$order->payment_complete();
			}

			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' WooCommerce payment_complete() processed and returned to ' . __METHOD__ . ' for order ' . $order_id );

			$order->add_order_note( sprintf(
			/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
				__( 'Order authorized using %s payment (%s). Set order to completed in WooCommerce when you have shipped the products, to capture the payment. Do this within 28 days, or the order will expire. To handle individual order lines, process the order via the Mollie Dashboard.', 'mollie-payments-for-woocommerce' ),
				$payment_method_title,
				$payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
			) );

			// Mark the order as processed and paid via Mollie
			$this->setOrderPaidAndProcessed( $order );

			// Remove (old) cancelled payments from this order
			$this->unsetCancelledMolliePaymentId( $order_id );

			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' processing order status update via Mollie plugin fully completed for order ' . $order_id );

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
			Mollie_WC_Plugin::debug( __METHOD__ . ' order at Mollie not authorized, so no processing for order ' . $order_id );

		}
	}

	/**
	 * @param WC_Order                   $order
	 * @param Mollie\Api\Resources\Order $payment
	 * @param string                     $payment_method_title
	 */
	public function onWebhookCompleted( WC_Order $order, $payment, $payment_method_title ) {

		// Get order ID in the correct way depending on WooCommerce version
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_id = $order->id;
		} else {
			$order_id = $order->get_id();
		}

		if ( $payment->isCompleted() ) {

			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $order_id );

			// WooCommerce 2.2.0 has the option to store the Payment transaction id.
			$woo_version = get_option( 'woocommerce_version', 'Unknown' );

			// TODO David: Keep WooCommerce payment_complete() here?
			if ( version_compare( $woo_version, '2.2.0', '>=' ) ) {
				$order->payment_complete( $payment->id );
			} else {
				$order->payment_complete();
			}

			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' WooCommerce payment_complete() processed and returned to ' . __METHOD__ . ' for order ' . $order_id );

			$order->add_order_note( sprintf(
			/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
				__( 'Order completed at Mollie for %s order (%s). At least one order line completed. ', 'mollie-payments-for-woocommerce' ),
				$payment_method_title,
				$payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
			) );

			// Mark the order as processed and paid via Mollie
			$this->setOrderPaidAndProcessed( $order );

			// Remove (old) cancelled payments from this order
			$this->unsetCancelledMolliePaymentId( $order_id );

			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' processing order status update via Mollie plugin fully completed for order ' . $order_id );

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
			Mollie_WC_Plugin::debug( __METHOD__ . ' order at Mollie not completed, so no further processing for order ' . $order_id );

		}
	}


	/**
	 * @param WC_Order                   $order
	 * @param Mollie\Api\Resources\Order $payment
	 * @param string                     $payment_method_title
	 */
	public function onWebhookCanceled( WC_Order $order, $payment, $payment_method_title ) {

		// Get order ID in the correct way depending on WooCommerce version
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			$order_id = $order->id;
		} else {
			$order_id = $order->get_id();
		}

		// Add messages to log
		Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $order_id );

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
			__( '%s order (%s) cancelled .', 'mollie-payments-for-woocommerce' ),
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
	 * @param WC_Order                   $order
	 * @param Mollie\Api\Resources\Order $payment
	 * @param string                     $payment_method_title
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

		// If WooCommerce Subscriptions is installed, process this failure as a subscription, otherwise as a regular order
		if ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {

			// New order status
			$new_order_status = Mollie_WC_Gateway_Abstract::STATUS_ON_HOLD;

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
						__( '%s renewal payment failed via Mollie (%s). You will need to manually review the payment and adjust product stocks if you use them.', 'mollie-payments-for-woocommerce' ),
						$payment_method_title,
						$payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
					),
					$restore_stock = false
				);
			}

			Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $order_id . ' and payment ' . $payment->id . ', renewal order payment failed, order set to On-Hold for shop-owner review.' );


			// Send a "Failed order" email to notify the admin
			$emails = WC()->mailer()->get_emails();
			if ( ! empty( $emails ) && ! empty( $order_id ) && ! empty( $emails['WC_Email_Failed_Order'] ) ) {
				$emails['WC_Email_Failed_Order']->trigger( $order_id );
			}
		} else {

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
		}

		Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $order_id . ' and payment ' . $payment->id . ', regular order payment failed.' );

	}

	/**
	 * @param WC_Order                   $order
	 * @param Mollie\Api\Resources\Order $payment
	 * @param string                     $payment_method_title
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

		// Check that this payment is the most recent, based on Mollie Payment ID from post meta, do not cancel the order if it isn't
		if ( $mollie_payment_id != $payment->id ) {
			Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $order_id . ' and payment ' . $payment->id . ', not processed because of a newer pending payment ' . $mollie_payment_id );

			$order->add_order_note( sprintf(
			/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
				__( '%s order expired (%s) but order not cancelled because of another pending payment (%s).', 'mollie-payments-for-woocommerce' ),
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
			__( '%s order (%s) expired .', 'mollie-payments-for-woocommerce' ),
			$payment_method_title,
			$payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
		) );

		// Remove (old) cancelled payments from this order
		$this->unsetCancelledMolliePaymentId( $order_id );

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


}