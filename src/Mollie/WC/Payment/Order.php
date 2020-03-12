<?php

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Refund;

class Mollie_WC_Payment_Order extends Mollie_WC_Payment_Object {

    const ACTION_AFTER_REFUND_AMOUNT_CREATED = Mollie_WC_Plugin::PLUGIN_ID . '_refund_amount_created';
    const ACTION_AFTER_REFUND_ORDER_CREATED = Mollie_WC_Plugin::PLUGIN_ID . '_refund_order_created';

	public static $paymentId;
	public static $customerId;
	public static $order;
	public static $payment;
	public static $shop_country;

    /**
     * @var Mollie_WC_Payment_OrderItemsRefunder
     */
    private $orderItemsRefunder;

    /**
     * Mollie_WC_Payment_Order constructor.
     * @param Mollie_WC_Payment_OrderItemsRefunder $orderItemsRefunder
     * @param $data
     */
    public function __construct(Mollie_WC_Payment_OrderItemsRefunder $orderItemsRefunder, $data)
    {
        $this->data = $data;
        $this->orderItemsRefunder = $orderItemsRefunder;
    }

	public function getPaymentObject( $payment_id, $test_mode = false, $use_cache = true ) {
		try {

			// Is test mode enabled?
			$settings_helper = Mollie_WC_Plugin::getSettingsHelper();
			$test_mode       = $settings_helper->isTestModeEnabled();

			self::$payment = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->orders->get( $payment_id, [ "embed" => "payments" ] );

			return parent::getPaymentObject( $payment_id, $test_mode = false, $use_cache = true );
		}
		catch ( ApiException $e ) {
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
				'amount'         => array (
					'currency' => Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ),
					'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $order->get_total(), Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ) )
				),
				'redirectUrl'    => $return_url,
				'webhookUrl'     => $webhook_url,
				'method'         => $mollie_method,
				'payment'        => array (
					'issuer' => $selected_issuer
				),
				'locale'         => $payment_locale,
				'billingAddress' => $billingAddress,
				'metadata'       => apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_payment_object_metadata', array (
					'order_id'     => $order->get_id(),
					'order_number' => $order->get_order_number()
				) ),
				'lines'          => $order_lines['lines'],
				'orderNumber'    => $order->get_order_number(), // TODO David: use order number or order id?
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

        $cardToken = mollieWooCommerceCardToken();
        if ($cardToken && isset($paymentRequestData['payment'])) {
            $paymentRequestData['payment']['cardToken'] = $cardToken;
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

    public function getMolliePaymentIdFromPaymentObject()
    {
        $payment = $this->getPaymentObject($this->data->id);

        if (isset($payment->_embedded->payments[0]->id)) {
            return $payment->_embedded->payments[0]->id;
        }
    }

    public function getMollieCustomerIdFromPaymentObject($payment = null)
    {
        if ($payment == null) {
            $payment = $this->data->id;
        }

        $payment = $this->getPaymentObject($payment);

        if (isset($payment->_embedded->payments[0]->customerId)) {
            return $payment->_embedded->payments[0]->customerId;
        }
    }

    public function getSequenceTypeFromPaymentObject($payment = null)
    {
        if ($payment == null) {
            $payment = $this->data->id;
        }

        $payment = $this->getPaymentObject($payment);

        if (isset($payment->_embedded->payments[0]->sequenceType)) {
            return $payment->_embedded->payments[0]->sequenceType;
        }
    }

    public function getMollieCustomerIbanDetailsFromPaymentObject($payment = null)
    {
        if ($payment == null) {
            $payment = $this->data->id;
        }

        $payment = $this->getPaymentObject($payment);

        if (isset($payment->_embedded->payments[0]->id)) {
            $actual_payment = new Mollie_WC_Payment_Payment($payment->_embedded->payments[0]->id);
            $actual_payment = $actual_payment->getPaymentObject($actual_payment->data);

            $iban_details['consumerName'] = $actual_payment->details->consumerName;
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
				__( 'Order completed at Mollie for %s order (%s). At least one order line completed. Remember: Completed status for an order at Mollie is not the same as Completed status in WooCommerce!', 'mollie-payments-for-woocommerce' ),
				$payment_method_title,
				$payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
			) );

			// TODO David: consider setting WooCommerce orders to completed when an order is completed in Mollie? Completed in WooCommerce is not the same as Completed in Mollie! From the API docs "When all order lines are completed or canceled, the order will be set to this status." Probably need to check if it should be converted to completed or cancelled.

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
        $order_id = mollieWooCommerceOrderId($order);

		// Add messages to log
		mollieWooCommerceDebug(__METHOD__ . " called for order {$order_id}" );

		// if the status is Completed|Refunded|Cancelled  DONT change the status to cancelled
        if ($this->isFinalOrderStatus($order)) {
            mollieWooCommerceDebug(
                __METHOD__
                . " called for payment {$order_id} has final status. Nothing to be done"
            );

            return;
        }

        //status is Pending|Failed|Processing|On-hold so Cancel
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

		// New order status
		$new_order_status = Mollie_WC_Gateway_Abstract::STATUS_FAILED;

		// Overwrite plugin-wide
		$new_order_status = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_failed', $new_order_status );

		// Overwrite gateway-wide
		$new_order_status = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_failed_' . $this->id, $new_order_status );

		$gateway = Mollie_WC_Plugin::getDataHelper()->getWcPaymentGatewayByOrder( $order );


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
			$mollie_payment_id = get_post_meta( $order_id, '_mollie_order_id', $single = true );
		} else {
			$order_id          = $order->get_id();
			$mollie_payment_id = $order->get_meta( '_mollie_order_id', true );
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

	/**
	 * Process a payment object refund
	 *
	 * @param WC_Order $order
	 * @param int    $order_id
	 * @param object $payment_object
	 * @param null   $amount
	 * @param string $reason
	 *
	 * @return bool|\WP_Error
	 */
	public function refund( WC_Order $order, $order_id, $payment_object, $amount = null, $reason = '' ) {

		Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $order_id . ' - Try to process refunds or cancels.' );

		try {
			$payment_object = $this->getPaymentObject( $payment_object->data );

			if ( ! $payment_object ) {

				$error_message = "Could not find active Mollie order for WooCommerce order ' . $order_id";

				Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $error_message );

				throw new Exception ( $error_message );
			}

			if ( ! ( $payment_object->isPaid() || $payment_object->isAuthorized() || $payment_object->isCompleted() ) ) {

				$error_message = "Can not cancel or refund $payment_object->id as order $order_id has status " . ucfirst( $payment_object->status ) . ", it should be at least Paid, Authorized or Completed.";

				Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $error_message );

				throw new Exception ( $error_message );
			}

			// Get all existing refunds
			$refunds = $order->get_refunds();

			// Get latest refund
			$woocommerce_refund = wc_get_order( $refunds[0] );

			// Get order items from refund
			$items = $woocommerce_refund->get_items( array ( 'line_item', 'fee', 'shipping' ) );

            if (empty ($items)) {
                return $this->refund_amount($order, $amount, $payment_object, $reason);
            }

            // Compare total amount of the refund to the combined totals of all refunded items,
            // if the refund total is greater than sum of refund items, merchant is also doing a
            // 'Refund amount', which the Mollie API does not support. In that case, stop entire
            // process and warn the merchant.

            $totals = 0;

            foreach ($items as $item_id => $item_data) {
                $totals += $item_data->get_total() + $item_data->get_total_tax();
            }

            $totals = number_format(abs($totals), 2); // WooCommerce - sum of all refund items
            $amount = number_format($amount, 2); // WooCommerce - refund amount

            if ($amount !== $totals) {
                $error_message = "The sum of refunds for all order lines is not identical to the refund amount, so this refund will be processed as a payment amount refund, not an order line refund.";
                $order->add_order_note($error_message);
                Mollie_WC_Plugin::debug(__METHOD__ . ' - ' . $error_message);

                return $this->refund_amount($order, $amount, $payment_object, $reason);
            }

            Mollie_WC_Plugin::debug('Try to process individual order item refunds or cancels.');

            try {
                return $this->orderItemsRefunder->refund(
                    $order,
                    $items,
                    $payment_object,
                    $reason
                );
            } catch (Mollie_WC_Payment_PartialRefundException $exception) {
                Mollie_WC_Plugin::debug(__METHOD__ . ' - ' . $exception->getMessage());
                return $this->refund_amount(
                    $order,
                    $amount,
                    $payment_object,
                    $reason
                );
            }
        } catch (Exception $exception) {
            $exceptionMessage = $exception->getMessage();
            Mollie_WC_Plugin::debug(__METHOD__ . ' - ' . $exceptionMessage);
            return new WP_Error(1, $exceptionMessage);
        }

        return false;
    }

    /**
     * @param $order
     * @param $order_id
     * @param $amount
     * @param $items
     * @param $payment_object
     * @param $reason
     *
     * @return bool
     * @throws ApiException
     * @deprecated Not recommended because merchant will be charged for every refunded item, use OrderItemsRefunder instead.
     */
	public function refund_order_items( $order, $order_id, $amount, $items, $payment_object, $reason ) {

		Mollie_WC_Plugin::debug( 'Try to process individual order item refunds or cancels.' );

		// Try to do the actual refunds or cancellations

		// Loop through items in the WooCommerce refund
		foreach ( $items as $key => $item ) {

			// Some merchants update orders with an order line with value 0, in that case skip processing that order line.
			$item_refund_amount_precheck = abs( $item->get_total() + $item->get_total_tax() );
			if ( $item_refund_amount_precheck == 0 ) {
				continue;
			}

			// Loop through items in the Mollie payment object (Order)
			foreach ( $payment_object->lines as $line ) {

				// If there is no metadata wth the order item ID, this order can't process individual order lines
				if ( empty( $line->metadata->order_item_id ) ) {
					$note_message = 'Refunds for this specific order can not be processed per order line. Trying to process this as an amount refund instead.';
					Mollie_WC_Plugin::debug( __METHOD__ . " - " . $note_message );

					return $this->refund_amount( $order, $amount, $payment_object, $reason );
				}

				// Get the Mollie order line information that we need later
				$original_order_item_id = $item->get_meta( '_refunded_item_id', true );
				$item_refund_amount     = abs( $item->get_total() + $item->get_total_tax() );

				if ( $original_order_item_id == $line->metadata->order_item_id ) {

					// Calculate the total refund amount for one order line
					$line_total_refund_amount = abs( $item->get_quantity() ) * $line->unitPrice->value;

					// Mollie doesn't allow a partial refund of the full amount or quantity of at least one order line, so when merchants try that, warn them and block the process
					if ( (number_format($line_total_refund_amount, 2 ) != number_format($item_refund_amount, 2 )) || ( abs($item->get_quantity()) < 1 ) ) {

						$note_message = sprintf( "Mollie doesn't allow a partial refund of the full amount or quantity of at least one order line. Use 'Refund amount' instead. The WooCommerce order item ID is %s, Mollie order line ID is %s.",
							$original_order_item_id,
							$line->id
						);

						Mollie_WC_Plugin::debug( __METHOD__ . " - Order $order_id: " . $note_message );
						throw new Exception ( $note_message );
					}

					// Is test mode enabled?
					$test_mode = Mollie_WC_Plugin::getSettingsHelper()->isTestModeEnabled();

					// Get the Mollie order
					$mollie_order = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->orders->get( $payment_object->id );

					$item_total_amount = abs(number_format($item->get_total() + $item->get_total_tax(), 2));

					// Prepare the order line to update
					if ( !empty( $line->discountAmount) ) {
						$lines = array (
							'lines' => array (
								array (
									'id'       => $line->id,
									'quantity' => abs( $item->get_quantity() ),
									'amount'      => array (
										'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $item_total_amount, Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ) ),
										'currency' => Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order )
									),
								)
							)
						);
					} else {
						$lines = array (
							'lines' => array (
								array (
									'id'       => $line->id,
									'quantity' => abs( $item->get_quantity() ),
								)
							)
						);
					}

					if ( $line->status == 'created' || $line->status == 'authorized' ) {

						// Returns null if successful.
						$refund = $mollie_order->cancelLines( $lines );

						Mollie_WC_Plugin::debug( __METHOD__ . ' - Cancelled order line: ' . abs( $item->get_quantity() ) . 'x ' . $item->get_name() . '. Mollie order line: ' . $line->id . ', payment object: ' . $payment_object->id . ', order: ' . $order_id . ', amount: ' . Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ) . wc_format_decimal( $item_refund_amount ) . ( ! empty( $reason ) ? ', reason: ' . $reason : '' ) );

						if ( $refund == null ) {
							$note_message = sprintf(
								__( '%sx %s cancelled for %s%s in WooCommerce and at Mollie.', 'mollie-payments-for-woocommerce' ),
								abs( $item->get_quantity() ),
								$item->get_name(),
								Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ),
								$item_refund_amount
							);
						}
					}

					if ( $line->status == 'paid' || $line->status == 'shipping' || $line->status == 'completed' ) {
						$lines['description'] = $reason;
						$refund               = $mollie_order->refund( $lines );

						Mollie_WC_Plugin::debug( __METHOD__ . ' - Refunded order line: ' . abs( $item->get_quantity() ) . 'x ' . $item->get_name() . '. Mollie order line: ' . $line->id . ', payment object: ' . $payment_object->id . ', order: ' . $order_id . ', amount: ' . Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ) . wc_format_decimal( $item_refund_amount ) . ( ! empty( $reason ) ? ', reason: ' . $reason : '' ) );

						$note_message = sprintf(
							__( '%sx %s refunded for %s%s in WooCommerce and at Mollie.%s Refund ID: %s.', 'mollie-payments-for-woocommerce' ),
							abs( $item->get_quantity() ),
							$item->get_name(),
							Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ),
							$item_refund_amount,
							( ! empty( $reason ) ? ' Reason: ' . $reason . '.' : '' ),
							$refund->id
						);
					}

                    do_action(
                        self::ACTION_AFTER_REFUND_ORDER_CREATED,
                        $refund,
                        $order
                    );

                    do_action_deprecated(
                        Mollie_WC_Plugin::PLUGIN_ID . '_refund_created',
                        [$refund, $order],
                        '5.3.1',
                        self::ACTION_AFTER_REFUND_PAYMENT_CREATED
                    );

					$order->add_order_note( $note_message );
					Mollie_WC_Plugin::debug( $note_message );

					// drop item from array
					unset( $items[ $item->get_id() ] );

				}

			}

		}

		// TODO David: add special version of
		// do_action( Mollie_WC_Plugin::PLUGIN_ID . '_refund_created', $refund, $order );

		return true;

	}

	/**
	 * @param $order
	 * @param $order_id
	 * @param $amount
	 * @param $payment_object
	 * @param $reason
	 *
	 * @return bool
	 * @throws ApiException|Exception
	 */
    public function refund_amount($order, $amount, $payment_object, $reason)
    {
        $orderId = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();

		Mollie_WC_Plugin::debug( 'Try to process an amount refund (not individual order line)' );

        $payment_object_payment = Mollie_WC_Plugin::getPaymentObject()->getActiveMolliePayment(
            $orderId
        );

		// Is test mode enabled?
		$test_mode = Mollie_WC_Plugin::getSettingsHelper()->isTestModeEnabled();

		if ( $payment_object->isCreated() || $payment_object->isAuthorized() || $payment_object->isShipping() ) {
			$note_message = 'Can not refund order amount that has status ' . ucfirst( $payment_object->status ) . ' at Mollie.';
			$order->add_order_note( $note_message );
			Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $note_message );
			throw new Exception ( $note_message );
		}

		if ( $payment_object->isPaid() || $payment_object->isShipping() || $payment_object->isCompleted() ) {

			$refund = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->payments->refund( $payment_object_payment, array (
				'amount'      => array (
					'currency' => Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ),
					'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $amount, Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ) )
				),
				'description' => $reason
			) );

			$note_message = sprintf(
				__( 'Amount refund of %s%s refunded in WooCommerce and at Mollie.%s Refund ID: %s.', 'mollie-payments-for-woocommerce' ),
				Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ),
				$amount,
				( ! empty( $reason ) ? ' Reason: ' . $reason . '.' : '' ),
				$refund->id
			);

			$order->add_order_note( $note_message );
			Mollie_WC_Plugin::debug( $note_message );

            /**
             * After Refund Amount Created
             *
             * @param Refund $refund
             * @param WC_Order $order
             * @param string $amount
             */
            do_action(self::ACTION_AFTER_REFUND_AMOUNT_CREATED, $refund, $order, $amount);

            do_action_deprecated(
                Mollie_WC_Plugin::PLUGIN_ID . '_refund_created',
                [$refund, $order],
                '5.3.1',
                self::ACTION_AFTER_REFUND_AMOUNT_CREATED
            );

			return true;

		}

		return false;
	}
}
