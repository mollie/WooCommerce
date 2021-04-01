<?php

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Refund;

class Mollie_WC_Payment_Order extends Mollie_WC_Payment_Object {

    const ACTION_AFTER_REFUND_AMOUNT_CREATED = Mollie_WC_Plugin::PLUGIN_ID . '_refund_amount_created';
    const ACTION_AFTER_REFUND_ORDER_CREATED = Mollie_WC_Plugin::PLUGIN_ID . '_refund_order_created';
    const MAXIMAL_LENGHT_ADDRESS = 100;
    const MAXIMAL_LENGHT_POSTALCODE = 20;
    const MAXIMAL_LENGHT_CITY = 200;
    const MAXIMAL_LENGHT_REGION = 200;

    static $paymentId;
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

	public function getPaymentObject( $paymentId, $testMode = false, $useCache = true ) {
		try {

			// Is test mode enabled?
			$settingsHelper = Mollie_WC_Plugin::getSettingsHelper();
			$testMode       = $settingsHelper->isTestModeEnabled();

			self::$payment = Mollie_WC_Plugin::getApiHelper()->getApiClient($testMode )->orders->get($paymentId, [ "embed" => "payments" ] );

			return parent::getPaymentObject($paymentId, $testMode = false, $useCache = true );
		}
		catch ( ApiException $e ) {
			Mollie_WC_Plugin::debug( __CLASS__ . __FUNCTION__ . ": Could not load payment $paymentId (" . ( $testMode ? 'test' : 'live' ) . "): " . $e->getMessage() . ' (' . get_class($e ) . ')' );
		}

		return null;
	}

	/**
	 * @param $order
	 * @param $customerId
	 *
	 * @return array
	 */
	public function getPaymentRequestData( $order, $customerId ) {
        $settingsHelper     = Mollie_WC_Plugin::getSettingsHelper();
		$paymentLocale      = $settingsHelper->getPaymentLocale();
		$storeCustomer      = $settingsHelper->shouldStoreCustomer();

		$gateway = wc_get_payment_gateway_by_order( $order );

		if ( ! $gateway || ! ( $gateway instanceof Mollie_WC_Gateway_Abstract ) ) {
			return array ( 'result' => 'failure' );
		}

		$mollieMethod   = $gateway->getMollieMethodId();
		$selectedIssuer = $gateway->getSelectedIssuer();
		$returnUrl      = $gateway->getReturnUrl( $order );
		$webhookUrl     = $gateway->getWebhookUrl( $order );
        $billingAddress = $this->createBillingAddress($order);
        $shippingAddress = $this->createShippingAddress($order);

        // Generate order lines for Mollie Orders
        $orderLinesHelper = Mollie_WC_Plugin::getOrderLinesHelper(
            self::$shop_country,
            $order
        );
        $orderLines = $orderLinesHelper->order_lines();

        // Build the Mollie order data
        $paymentRequestData = array(
            'amount' => array(
                'currency' => mollieWooCommerceGetDataHelper(
                )->getOrderCurrency($order),
                'value' => mollieWooCommerceGetDataHelper(
                )->formatCurrencyValue(
                    $order->get_total(),
                    mollieWooCommerceGetDataHelper()->getOrderCurrency($order)
                )
            ),
            'redirectUrl' => $returnUrl,
            'webhookUrl' => $webhookUrl,
            'method' => $mollieMethod,
            'payment' => array(
                'issuer' => $selectedIssuer
            ),
            'locale' => $paymentLocale,
            'billingAddress' => $billingAddress,
            'metadata' => apply_filters(
                Mollie_WC_Plugin::PLUGIN_ID . '_payment_object_metadata',
                array(
                    'order_id' => $order->get_id(),
                    'order_number' => $order->get_order_number()
                )
            ),
            'lines' => $orderLines['lines'],
            'orderNumber' => $order->get_order_number(),
            // TODO David: use order number or order id?
        );

			// Add sequenceType for subscriptions first payments
			if ( class_exists( 'WC_Subscriptions' ) && class_exists( 'WC_Subscriptions_Admin' ) ) {
				if ( mollieWooCommerceGetDataHelper()->isWcSubscription($order->get_id() ) ) {

					// See get_available_payment_gateways() in woocommerce-subscriptions/includes/gateways/class-wc-subscriptions-payment-gateways.php
					$disable_automatic_payments = ( 'yes' == get_option( WC_Subscriptions_Admin::$option_prefix . '_turn_off_automatic_payments', 'no' ) ) ? true : false;
					$supports_subscriptions     = $gateway->supports( 'subscriptions' );

					if ( $supports_subscriptions == true && $disable_automatic_payments == false ) {
						$paymentRequestData['payment']['sequenceType'] = 'first';
					}
				}
			}

        $dataHelper = Mollie_WC_Plugin::getDataHelper();
		$orderId = $order->get_id();
        if ($dataHelper->isSubscription($orderId)) {
            $supports_subscriptions     = $gateway->supports( 'subscriptions' );

            if ( $supports_subscriptions == true ) {
                $paymentRequestData['payment']['sequenceType'] = 'first';
            }
        }

        // Only add shippingAddress if all required fields are set
        if (!empty($shippingAddress->streetAndNumber)
            && !empty($shippingAddress->postalCode)
            && !empty($shippingAddress->city)
            && !empty($shippingAddress->country)
        ) {
            $paymentRequestData['shippingAddress'] = $shippingAddress;
        }

        // Only store customer at Mollie if setting is enabled
        if ($storeCustomer) {
            $paymentRequestData['payment']['customerId'] = $customerId;
        }

        $cardToken = mollieWooCommerceCardToken();
        if ($cardToken && isset($paymentRequestData['payment'])) {
            $paymentRequestData['payment']['cardToken'] = $cardToken;
        }

        $applePayToken = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_STRING);
        if ($applePayToken && isset($paymentRequestData['payment'])) {
            $encodedApplePayToken = json_encode($applePayToken);
            $paymentRequestData['payment']['applePayPaymentToken'] = $encodedApplePayToken;
        }

        return $paymentRequestData;
	}

	public function setActiveMolliePayment( $orderId ) {

		self::$paymentId  = $this->getMolliePaymentIdFromPaymentObject();
		self::$customerId = $this->getMollieCustomerIdFromPaymentObject();

		self::$order = wc_get_order($orderId );
		self::$order->update_meta_data( '_mollie_order_id', $this->data->id );
		self::$order->save();

		return parent::setActiveMolliePayment($orderId );
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
            $actualPayment = new Mollie_WC_Payment_Payment($payment->_embedded->payments[0]->id);
            $actualPayment = $actualPayment->getPaymentObject($actualPayment->data);

            $ibanDetails['consumerName'] = $actualPayment->details->consumerName;
            $ibanDetails['consumerAccount'] = $actualPayment->details->consumerAccount;
        }

        return $ibanDetails;
    }

	/**
	 * @param WC_Order                   $order
	 * @param Mollie\Api\Resources\Order $payment
	 * @param string                     $paymentMethodTitle
	 */
	public function onWebhookPaid( WC_Order $order, $payment, $paymentMethodTitle ) {

        $orderId = $order->get_id();
		if ( $payment->isPaid() ) {

			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . " called for order {$orderId}" );

            $order->payment_complete($payment->id);

			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' WooCommerce payment_complete() processed and returned to ' . __METHOD__ . " for order {$orderId}" );

			$order->add_order_note( sprintf(
			/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
				__( 'Order completed using %s payment (%s).', 'mollie-payments-for-woocommerce' ),
				$paymentMethodTitle,
				$payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
			) );

			// Mark the order as processed and paid via Mollie
			$this->setOrderPaidAndProcessed( $order );

			// Remove (old) cancelled payments from this order
			$this->unsetCancelledMolliePaymentId( $orderId );

			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . " processing paid order via Mollie plugin fully completed for order {$orderId}" );
            //update payment so it can be refunded directly
            $this->updatePaymentDataWithOrderData($payment, $orderId);
            // Add a message to log
            Mollie_WC_Plugin::debug(
                __METHOD__ . ' updated payment with webhook and metadata '
            );

            // Subscription processing
            $this->deleteSubscriptionFromPending($order);
		} else {
			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . " payment at Mollie not paid, so no processing for order {$orderId}" );
		}
	}

	/**
	 * @param WC_Order                   $order
	 * @param Mollie\Api\Resources\Order $payment
	 * @param string                     $paymentMethodTitle
	 */
	public function onWebhookAuthorized( WC_Order $order, $payment, $paymentMethodTitle ) {

		// Get order ID in the correct way depending on WooCommerce version
        $orderId = $order->get_id();

		if ( $payment->isAuthorized() ) {

			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $orderId );

			// WooCommerce 2.2.0 has the option to store the Payment transaction id.
            $order->payment_complete($payment->id);

			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' WooCommerce payment_complete() processed and returned to ' . __METHOD__ . ' for order ' . $orderId );

			$order->add_order_note( sprintf(
			/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                                        __( 'Order authorized using %s payment (%s). Set order to completed in WooCommerce when you have shipped the products, to capture the payment. Do this within 28 days, or the order will expire. To handle individual order lines, process the order via the Mollie Dashboard.', 'mollie-payments-for-woocommerce' ),
                                        $paymentMethodTitle,
                                        $payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
			) );

			// Mark the order as processed and paid via Mollie
			$this->setOrderPaidAndProcessed( $order );

			// Remove (old) cancelled payments from this order
			$this->unsetCancelledMolliePaymentId( $orderId );

			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' processing order status update via Mollie plugin fully completed for order ' . $orderId );

			// Subscription processing
            $this->deleteSubscriptionFromPending($order);

		} else {
			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' order at Mollie not authorized, so no processing for order ' . $orderId );
		}
	}

	/**
	 * @param WC_Order                   $order
	 * @param Mollie\Api\Resources\Order $payment
	 * @param string                     $paymentMethodTitle
	 */
	public function onWebhookCompleted( WC_Order $order, $payment, $paymentMethodTitle ) {

        $orderId = $order->get_id();

		if ( $payment->isCompleted() ) {

			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $orderId );

            $order->payment_complete($payment->id);
			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' WooCommerce payment_complete() processed and returned to ' . __METHOD__ . ' for order ' . $orderId );

			$order->add_order_note( sprintf(
			/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                                        __( 'Order completed at Mollie for %s order (%s). At least one order line completed. Remember: Completed status for an order at Mollie is not the same as Completed status in WooCommerce!', 'mollie-payments-for-woocommerce' ),
                                        $paymentMethodTitle,
                                        $payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
			) );

			// Mark the order as processed and paid via Mollie
			$this->setOrderPaidAndProcessed( $order );

			// Remove (old) cancelled payments from this order
			$this->unsetCancelledMolliePaymentId( $orderId );

			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' processing order status update via Mollie plugin fully completed for order ' . $orderId );

			// Subscription processing
			$this->deleteSubscriptionFromPending($order);
		} else {
			// Add messages to log
			Mollie_WC_Plugin::debug( __METHOD__ . ' order at Mollie not completed, so no further processing for order ' . $orderId );
		}
	}


	/**
	 * @param WC_Order                   $order
	 * @param Mollie\Api\Resources\Order $payment
	 * @param string                     $paymentMethodTitle
	 */
	public function onWebhookCanceled( WC_Order $order, $payment, $paymentMethodTitle ) {

		// Get order ID in the correct way depending on WooCommerce version
        $orderId = $order->get_id();

		// Add messages to log
		mollieWooCommerceDebug(__METHOD__ . " called for order {$orderId}" );

		// if the status is Completed|Refunded|Cancelled  DONT change the status to cancelled
        if ($this->isFinalOrderStatus($order)) {
            mollieWooCommerceDebug(
                __METHOD__
                . " called for payment {$orderId} has final status. Nothing to be done"
            );

            return;
        }

        //status is Pending|Failed|Processing|On-hold so Cancel
		$this->unsetActiveMolliePayment( $orderId, $payment->id );
		$this->setCancelledMolliePaymentId( $orderId, $payment->id );

		// What status does the user want to give orders with cancelled payments?
		$settingsHelper                 = Mollie_WC_Plugin::getSettingsHelper();
		$orderStatusCancelledPayments = $settingsHelper->getOrderStatusCancelledPayments();

		// New order status
		if ( $orderStatusCancelledPayments == 'pending' || $orderStatusCancelledPayments == null ) {
			$newOrderStatus = Mollie_WC_Gateway_Abstract::STATUS_PENDING;
		} elseif ( $orderStatusCancelledPayments == 'cancelled' ) {
			$newOrderStatus = Mollie_WC_Gateway_Abstract::STATUS_CANCELLED;
		}

		// Overwrite plugin-wide
		$newOrderStatus = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_cancelled', $newOrderStatus );

		// Overwrite gateway-wide
		$newOrderStatus = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_cancelled_' . $this->id, $newOrderStatus );

		// Update order status, but only if there is no payment started by another gateway
        $this->maybeUpdateStatus(
            $order,
            $newOrderStatus,
            $orderId,
            $paymentMethodTitle,
            $payment
        );

		// User cancelled payment on Mollie or issuer page, add a cancel note.. do not cancel order.
		$order->add_order_note( sprintf(
		/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                                    __( '%s order (%s) cancelled .', 'mollie-payments-for-woocommerce' ),
                                    $paymentMethodTitle,
                                    $payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
		) );
        $this->deleteSubscriptionFromPending($order);
    }

	/**
	 * @param WC_Order                   $order
	 * @param Mollie\Api\Resources\Order $payment
	 * @param string                     $paymentMethodTitle
	 */
	public function onWebhookFailed( WC_Order $order, $payment, $paymentMethodTitle ) {

        $orderId = $order->get_id();

		// Add messages to log
		Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $orderId );

		// New order status
		$newOrderStatus = Mollie_WC_Gateway_Abstract::STATUS_FAILED;

		// Overwrite plugin-wide
		$newOrderStatus = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_failed', $newOrderStatus );

		// Overwrite gateway-wide
		$newOrderStatus = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_failed_' . $this->id, $newOrderStatus );

		$gateway = wc_get_payment_gateway_by_order( $order );


		// If WooCommerce Subscriptions is installed, process this failure as a subscription, otherwise as a regular order
		// Update order status for order with failed payment, don't restore stock
        $this->failedSubscriptionProcess(
            $orderId,
            $gateway,
            $order,
            $newOrderStatus,
            $paymentMethodTitle,
            $payment
        );

		Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $orderId . ' and payment ' . $payment->id . ', regular order payment failed.' );

	}

	/**
	 * @param WC_Order                   $order
	 * @param Mollie\Api\Resources\Order $payment
	 * @param string                     $paymentMethodTitle
	 */
	public function onWebhookExpired( WC_Order $order, $payment, $paymentMethodTitle ) {

        $orderId          = $order->get_id();
        $molliePaymentId = $order->get_meta( '_mollie_order_id', true );

		// Add messages to log
		Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $orderId );

		// Check that this payment is the most recent, based on Mollie Payment ID from post meta, do not cancel the order if it isn't
		if ( $molliePaymentId != $payment->id ) {
			Mollie_WC_Plugin::debug( __METHOD__ . ' called for order ' . $orderId . ' and payment ' . $payment->id . ', not processed because of a newer pending payment ' . $molliePaymentId );

			$order->add_order_note( sprintf(
			/* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                                        __( '%s order expired (%s) but not cancelled because of another pending payment (%s).', 'mollie-payments-for-woocommerce' ),
                                        $paymentMethodTitle,
                                        $payment->id . ( $payment->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' ),
                                        $molliePaymentId
			) );

			return;
		}

		// New order status
		$newOrderStatus = Mollie_WC_Gateway_Abstract::STATUS_CANCELLED;

		// Overwrite plugin-wide
		$newOrderStatus = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_expired', $newOrderStatus );

		// Overwrite gateway-wide
		$newOrderStatus = apply_filters( Mollie_WC_Plugin::PLUGIN_ID . '_order_status_expired_' . $this->id, $newOrderStatus );

		// Update order status, but only if there is no payment started by another gateway
        $this->maybeUpdateStatus(
            $order,
            $newOrderStatus,
            $orderId,
            $paymentMethodTitle,
            $payment
        );

        // Remove (old) cancelled payments from this order
		$this->unsetCancelledMolliePaymentId( $orderId );

		// Subscription processing
        $this->deleteSubscriptionFromPending($order);
	}

	/**
	 * Process a payment object refund
	 *
	 * @param WC_Order $order
	 * @param int      $orderId
	 * @param object   $paymentObject
	 * @param null     $amount
	 * @param string   $reason
	 *
	 * @return bool|\WP_Error
	 */
	public function refund( WC_Order $order, $orderId, $paymentObject, $amount = null, $reason = '' ) {

		Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $orderId . ' - Try to process refunds or cancels.' );

		try {
			$paymentObject = $this->getPaymentObject($paymentObject->data );

			if ( ! $paymentObject ) {

				$errorMessage = "Could not find active Mollie order for WooCommerce order ' . $orderId";

				Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $errorMessage );

				throw new Exception ( $errorMessage );
			}

			if ( ! ( $paymentObject->isPaid() || $paymentObject->isAuthorized() || $paymentObject->isCompleted() ) ) {

				$errorMessage = "Can not cancel or refund $paymentObject->id as order $orderId has status " . ucfirst($paymentObject->status ) . ", it should be at least Paid, Authorized or Completed.";

				Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $errorMessage );

				throw new Exception ( $errorMessage );
			}

			// Get all existing refunds
			$refunds = $order->get_refunds();

			// Get latest refund
			$woocommerceRefund = wc_get_order( $refunds[0] );

			// Get order items from refund
			$items = $woocommerceRefund->get_items( array ( 'line_item', 'fee', 'shipping' ) );

            if (empty ($items)) {
                return $this->refund_amount($order, $amount, $paymentObject, $reason);
            }

            // Compare total amount of the refund to the combined totals of all refunded items,
            // if the refund total is greater than sum of refund items, merchant is also doing a
            // 'Refund amount', which the Mollie API does not support. In that case, stop entire
            // process and warn the merchant.

            $totals = 0;

            foreach ($items as $itemId => $itemData) {
                $totals += $itemData->get_total() + $itemData->get_total_tax();
            }

            $totals       = number_format(abs($totals), 2); // WooCommerce - sum of all refund items
            $checkAmount = number_format($amount, 2); // WooCommerce - refund amount

            if ($checkAmount !== $totals) {
                $errorMessage = "The sum of refunds for all order lines is not identical to the refund amount, so this refund will be processed as a payment amount refund, not an order line refund.";
                $order->add_order_note($errorMessage);
                Mollie_WC_Plugin::debug(__METHOD__ . ' - ' . $errorMessage);

                return $this->refund_amount($order, $amount, $paymentObject, $reason);
            }

            Mollie_WC_Plugin::debug('Try to process individual order item refunds or cancels.');

            try {
                return $this->orderItemsRefunder->refund(
                    $order,
                    $items,
                    $paymentObject,
                    $reason
                );
            } catch (Mollie_WC_Payment_PartialRefundException $exception) {
                Mollie_WC_Plugin::debug(__METHOD__ . ' - ' . $exception->getMessage());
                return $this->refund_amount(
                    $order,
                    $amount,
                    $paymentObject,
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
     * @param $orderId
     * @param $amount
     * @param $items
     * @param $paymentObject
     * @param $reason
     *
     * @return bool
     * @throws ApiException
     * @deprecated Not recommended because merchant will be charged for every refunded item, use OrderItemsRefunder instead.
     */
	public function refund_order_items( $order, $orderId, $amount, $items, $paymentObject, $reason ) {

		Mollie_WC_Plugin::debug( 'Try to process individual order item refunds or cancels.' );

		// Try to do the actual refunds or cancellations

		// Loop through items in the WooCommerce refund
		foreach ( $items as $key => $item ) {

			// Some merchants update orders with an order line with value 0, in that case skip processing that order line.
			$itemRefundAmountPrecheck = abs( $item->get_total() + $item->get_total_tax() );
			if ( $itemRefundAmountPrecheck == 0 ) {
				continue;
			}

			// Loop through items in the Mollie payment object (Order)
			foreach ( $paymentObject->lines as $line ) {

				// If there is no metadata wth the order item ID, this order can't process individual order lines
				if ( empty( $line->metadata->order_item_id ) ) {
					$noteMessage = 'Refunds for this specific order can not be processed per order line. Trying to process this as an amount refund instead.';
					Mollie_WC_Plugin::debug( __METHOD__ . " - " . $noteMessage );

					return $this->refund_amount($order, $amount, $paymentObject, $reason );
				}

				// Get the Mollie order line information that we need later
				$originalOrderItemId = $item->get_meta( '_refunded_item_id', true );
				$itemRefundAmount     = abs( $item->get_total() + $item->get_total_tax() );

				if ( $originalOrderItemId == $line->metadata->order_item_id ) {

					// Calculate the total refund amount for one order line
					$lineTotalRefundAmount = abs( $item->get_quantity() ) * $line->unitPrice->value;

					// Mollie doesn't allow a partial refund of the full amount or quantity of at least one order line, so when merchants try that, warn them and block the process
					if ( (number_format($lineTotalRefundAmount, 2 ) != number_format($itemRefundAmount, 2 )) || ( abs($item->get_quantity()) < 1 ) ) {

						$noteMessage = sprintf( "Mollie doesn't allow a partial refund of the full amount or quantity of at least one order line. Use 'Refund amount' instead. The WooCommerce order item ID is %s, Mollie order line ID is %s.",
							$originalOrderItemId,
							$line->id
						);

						Mollie_WC_Plugin::debug( __METHOD__ . " - Order $orderId: " . $noteMessage );
						throw new Exception ( $noteMessage );
					}

					// Is test mode enabled?
					$testMode = Mollie_WC_Plugin::getSettingsHelper()->isTestModeEnabled();

					// Get the Mollie order
					$mollieOrder = Mollie_WC_Plugin::getApiHelper()->getApiClient( $testMode )->orders->get($paymentObject->id );

					$itemTotalAmount = abs(number_format($item->get_total() + $item->get_total_tax(), 2));

					// Prepare the order line to update
					if ( !empty( $line->discountAmount) ) {
						$lines = array (
							'lines' => array (
								array (
									'id'       => $line->id,
									'quantity' => abs( $item->get_quantity() ),
									'amount'      => array (
										'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $itemTotalAmount, Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ) ),
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
						$refund = $mollieOrder->cancelLines( $lines );

						Mollie_WC_Plugin::debug( __METHOD__ . ' - Cancelled order line: ' . abs( $item->get_quantity() ) . 'x ' . $item->get_name() . '. Mollie order line: ' . $line->id . ', payment object: ' . $paymentObject->id . ', order: ' . $orderId . ', amount: ' . Mollie_WC_Plugin::getDataHelper()->getOrderCurrency($order ) . wc_format_decimal($itemRefundAmount ) . ( ! empty( $reason ) ? ', reason: ' . $reason : '' ) );

						if ( $refund == null ) {
							$noteMessage = sprintf(
								__( '%sx %s cancelled for %s%s in WooCommerce and at Mollie.', 'mollie-payments-for-woocommerce' ),
								abs( $item->get_quantity() ),
								$item->get_name(),
								Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ),
								$itemRefundAmount
							);
						}
					}

					if ( $line->status == 'paid' || $line->status == 'shipping' || $line->status == 'completed' ) {
						$lines['description'] = $reason;
						$refund               = $mollieOrder->refund( $lines );

						Mollie_WC_Plugin::debug( __METHOD__ . ' - Refunded order line: ' . abs( $item->get_quantity() ) . 'x ' . $item->get_name() . '. Mollie order line: ' . $line->id . ', payment object: ' . $paymentObject->id . ', order: ' . $orderId . ', amount: ' . Mollie_WC_Plugin::getDataHelper()->getOrderCurrency($order ) . wc_format_decimal($itemRefundAmount ) . ( ! empty( $reason ) ? ', reason: ' . $reason : '' ) );

						$noteMessage = sprintf(
							__( '%sx %s refunded for %s%s in WooCommerce and at Mollie.%s Refund ID: %s.', 'mollie-payments-for-woocommerce' ),
							abs( $item->get_quantity() ),
							$item->get_name(),
							Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ),
							$itemRefundAmount,
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

					$order->add_order_note( $noteMessage );
					Mollie_WC_Plugin::debug( $noteMessage );

					// drop item from array
					unset( $items[ $item->get_id() ] );

				}

			}

		}

		return true;
	}

	/**
	 * @param $order
	 * @param $order_id
	 * @param $amount
	 * @param $paymentObject
	 * @param $reason
	 *
	 * @return bool
	 * @throws ApiException|Exception
	 */
    public function refund_amount($order, $amount, $paymentObject, $reason)
    {
        $orderId = $order->get_id();

		Mollie_WC_Plugin::debug( 'Try to process an amount refund (not individual order line)' );

        $paymentObjectPayment = Mollie_WC_Plugin::getPaymentObject()->getActiveMolliePayment(
            $orderId
        );

		$test_mode = Mollie_WC_Plugin::getSettingsHelper()->isTestModeEnabled();

		if ( $paymentObject->isCreated() || $paymentObject->isAuthorized() || $paymentObject->isShipping() ) {
			$noteMessage = 'Can not refund order amount that has status ' . ucfirst($paymentObject->status ) . ' at Mollie.';
			$order->add_order_note( $noteMessage );
			Mollie_WC_Plugin::debug( __METHOD__ . ' - ' . $noteMessage );
			throw new Exception ( $noteMessage );
		}

		if ( $paymentObject->isPaid() || $paymentObject->isShipping() || $paymentObject->isCompleted() ) {

			$refund = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->payments->refund( $paymentObjectPayment, array (
				'amount'      => array (
					'currency' => Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ),
					'value'    => Mollie_WC_Plugin::getDataHelper()->formatCurrencyValue( $amount, Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ) )
				),
				'description' => $reason
			) );

			$noteMessage = sprintf(
				__( 'Amount refund of %s%s refunded in WooCommerce and at Mollie.%s Refund ID: %s.', 'mollie-payments-for-woocommerce' ),
				Mollie_WC_Plugin::getDataHelper()->getOrderCurrency( $order ),
				$amount,
				( ! empty( $reason ) ? ' Reason: ' . $reason . '.' : '' ),
				$refund->id
			);

			$order->add_order_note( $noteMessage );
			Mollie_WC_Plugin::debug( $noteMessage );

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

    /**
     * @param Mollie\Api\Resources\Order $order
     * @param int                     $orderId
     */
    public function updatePaymentDataWithOrderData($order, $orderId)
    {
        $paymentCollection = $order->payments();
        foreach ($paymentCollection as $payment) {
            $payment->webhookUrl = $order->webhookUrl;
            $payment->metadata = ['order_id' => $orderId];
            $payment->update();
        }
    }

    /**
     * Method that shortens the field to a certain length
     *
     * @param string $field
     * @param int    $maximalLength
     *
     * @return null|string
     */
    protected function maximalFieldLengths($field, $maximalLength)
    {
        if (!is_string($field)) {
            return null;
        }
        if (is_int($maximalLength) && strlen($field) > $maximalLength) {
            $field = substr($field, 0, $maximalLength);
            $field = !$field ? null : $field;
        }

        return $field;
    }

    /**
     * @param WC_Order                    $order
     * @param                             $newOrderStatus
     * @param                             $orderId
     * @param                             $paymentMethodTitle
     * @param \Mollie\Api\Resources\Order $payment
     */
    protected function maybeUpdateStatus(
        WC_Order $order,
        $newOrderStatus,
        $orderId,
        $paymentMethodTitle,
        \Mollie\Api\Resources\Order $payment
    ) {
        if (!$this->isOrderPaymentStartedByOtherGateway($order)) {
            $gateway = wc_get_payment_gateway_by_order($order);

            if ($gateway) {
                $gateway->updateOrderStatus($order, $newOrderStatus);
            }
        } else {
            $this->informNotUpdatingStatus($orderId, $this->id, $order);
        }

        $order->add_order_note(
            sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                __(
                    '%s order (%s) expired .',
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

    /**
     * @param $order
     * @return stdClass
     */
    protected function createBillingAddress($order)
    {
        // Setup billing and shipping objects
        $billingAddress = new stdClass();

        // Get user details
        $billingAddress->givenName = (ctype_space(
            $order->get_billing_first_name()
        )) ? null : $order->get_billing_first_name();
        $billingAddress->familyName = (ctype_space(
            $order->get_billing_last_name()
        )) ? null : $order->get_billing_last_name();
        $billingAddress->email = (ctype_space($order->get_billing_email()))
            ? null : $order->get_billing_email();
        // Create billingAddress object
        $billingAddress->streetAndNumber = (ctype_space(
            $order->get_billing_address_1()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_address_1(),
                self::MAXIMAL_LENGHT_ADDRESS
            );
        $billingAddress->streetAdditional = (ctype_space(
            $order->get_billing_address_2()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_address_2(),
                self::MAXIMAL_LENGHT_ADDRESS
            );
        $billingAddress->postalCode = (ctype_space(
            $order->get_billing_postcode()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_postcode(),
                self::MAXIMAL_LENGHT_POSTALCODE
            );
        $billingAddress->city = (ctype_space($order->get_billing_city()))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_city(),
                self::MAXIMAL_LENGHT_CITY
            );
        $billingAddress->region = (ctype_space($order->get_billing_state()))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_state(),
                self::MAXIMAL_LENGHT_REGION
            );
        $billingAddress->country = (ctype_space($order->get_billing_country()))
            ? null
            : $this->maximalFieldLengths(
                $order->get_billing_country(),
                self::MAXIMAL_LENGHT_REGION
            );
        return $billingAddress;
    }

    /**
     * @param $order
     * @return stdClass
     */
    protected function createShippingAddress($order)
    {
        $shippingAddress = new stdClass();
        // Get user details
        $shippingAddress->givenName = (ctype_space(
            $order->get_shipping_first_name()
        )) ? null : $order->get_shipping_first_name();
        $shippingAddress->familyName = (ctype_space(
            $order->get_shipping_last_name()
        )) ? null : $order->get_shipping_last_name();
        $shippingAddress->email = (ctype_space($order->get_billing_email()))
            ? null
            : $order->get_billing_email(); // WooCommerce doesn't have a shipping email


        // Create shippingAddress object
        $shippingAddress->streetAndNumber = (ctype_space(
            $order->get_shipping_address_1()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_address_1(),
                self::MAXIMAL_LENGHT_ADDRESS
            );
        $shippingAddress->streetAdditional = (ctype_space(
            $order->get_shipping_address_2()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_address_2(),
                self::MAXIMAL_LENGHT_ADDRESS
            );
        $shippingAddress->postalCode = (ctype_space(
            $order->get_shipping_postcode()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_postcode(),
                self::MAXIMAL_LENGHT_POSTALCODE
            );
        $shippingAddress->city = (ctype_space($order->get_shipping_city()))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_city(),
                self::MAXIMAL_LENGHT_CITY
            );
        $shippingAddress->region = (ctype_space($order->get_shipping_state()))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_state(),
                self::MAXIMAL_LENGHT_REGION
            );
        $shippingAddress->country = (ctype_space(
            $order->get_shipping_country()
        ))
            ? null
            : $this->maximalFieldLengths(
                $order->get_shipping_country(),
                self::MAXIMAL_LENGHT_REGION
            );
        return $shippingAddress;
    }
}
