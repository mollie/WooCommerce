<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Payment;
use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\Payment\MollieOrder;
use Mollie\WooCommerce\Payment\MolliePayment;
use Mollie\WooCommerce\Plugin;
use Psr\Log\LoggerInterface as Logger;

class PaymentService
{
    protected $gateway;
    /**
     * @var NoticeInterface
     */
    protected $notice;
    /**
     * @var Logger
     */
    protected $logger;


	/**
	 * PaymentService constructor.
	 */
	public function __construct(NoticeInterface $notice, Logger $logger)
	{
	    $this->notice = $notice;
	    $this->logger = $logger;
	}

    public function setGateway($gateway)
    {
        $this->gateway = $gateway;
    }

    public function processPayment($order_id, $paymentConfirmationAfterCoupleOfDays)
    {
        $dataHelper = Plugin::getDataHelper();
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            $this->logger->log( \WC_Log_Levels::DEBUG,  $this->gateway->id . ': Could not process payment, order ' . $order_id . ' not found.' );

            $this->notice->addNotice(
                'error',
                sprintf(
                    __(
                        'Could not load order %s',
                        'mollie-payments-for-woocommerce'
                    ),
                    $order_id
                )
            );

            return array ( 'result' => 'failure' );
        }

        $orderId = $order->get_id();
        $this->logger->log( \WC_Log_Levels::DEBUG,  "{$this->gateway->id}: Start process_payment for order {$orderId}", [true] );

        $initial_order_status = $this->getInitialOrderStatus($paymentConfirmationAfterCoupleOfDays);

        // Overwrite plugin-wide
        $initial_order_status = apply_filters( Plugin::PLUGIN_ID . '_initial_order_status', $initial_order_status );

        // Overwrite gateway-wide
        $initial_order_status = apply_filters( Plugin::PLUGIN_ID . '_initial_order_status_' . $this->id, $initial_order_status );

        $settings_helper = Plugin::getSettingsHelper();

        // Is test mode enabled?
        $test_mode          = $settings_helper->isTestModeEnabled();
        $customer_id        = $this->getUserMollieCustomerId( $order, $test_mode );

        //
        // PROCESS SUBSCRIPTION SWITCH - If this is a subscription switch and customer has a valid mandate, process the order internally
        //
        if ( ( '0.00' === $order->get_total() ) && ( Plugin::getDataHelper()->isWcSubscription($order_id ) == true ) &&
            0 != $order->get_user_id() && ( wcs_order_contains_switch( $order ) )
        ) {

            try {
                $paymentObject = Plugin::getPaymentFactoryHelper()->getPaymentObject(
                    self::PAYMENT_METHOD_TYPE_PAYMENT
                );
                $paymentRequestData = $paymentObject->getPaymentRequestData($order, $customer_id);
                $data = array_filter($paymentRequestData);
                $data = apply_filters('woocommerce_' . $this->id . '_args', $data, $order);

                $this->logger->log( \WC_Log_Levels::DEBUG,  $this->id . ': Subscription switch started, fetching mandate(s) for order #' . $order_id );
                $mandates = Plugin::getApiHelper()->getApiClient( $test_mode )->customers->get( $customer_id )->mandates();
                $validMandate = false;
                foreach ( $mandates as $mandate ) {
                    if ( $mandate->status == 'valid' ) {
                        $validMandate   = true;
                        $data['method'] = $mandate->method;
                        break;
                    }
                }
                if ( $validMandate ) {

                    $order->payment_complete();

                    $order->add_order_note( sprintf(
                                                __( 'Order completed internally because of an existing valid mandate at Mollie.', 'mollie-payments-for-woocommerce' ) ) );

                    $this->logger->log( \WC_Log_Levels::DEBUG,  $this->id . ': Subscription switch completed, valid mandate for order #' . $order_id );

                    return array (
                        'result'   => 'success',
                        'redirect' => $this->get_return_url( $order ),
                    );

                } else {
                    $this->logger->log( \WC_Log_Levels::DEBUG,  $this->id . ': Subscription switch failed, no valid mandate for order #' . $order_id );
                    $this->notice->addNotice(
                        'error',
                        __(
                            'Subscription switch failed, no valid mandate found. Place a completely new order to change your subscription.',
                            'mollie-payments-for-woocommerce'
                        )
                    );
                    throw new Mollie\Api\Exceptions\ApiException( __( 'Failed switching subscriptions, no valid mandate.', 'mollie-payments-for-woocommerce' ) );
                }
            }
            catch ( Mollie\Api\Exceptions\ApiException $e ) {
                if ( $e->getField() ) {
                    throw $e;
                }
            }

            return array ( 'result' => 'failure' );

        }

        $molliePaymentType = $this->paymentTypeBasedOnGateway();
        $molliePaymentType = $this->paymentTypeBasedOnProducts($order,$molliePaymentType);
        try {
            $paymentObject = Plugin::getPaymentFactoryHelper()
                ->getPaymentObject(
                    $molliePaymentType
                );
        } catch (ApiException $exception) {
            $this->logger->log( \WC_Log_Levels::DEBUG, $exception->getMessage());
            return array('result' => 'failure');
        }

        //
        // TRY PROCESSING THE PAYMENT AS MOLLIE ORDER OR MOLLIE PAYMENT
        //

        try {
            $paymentObject = $this->processPaymentForMollie(
                $molliePaymentType,
                $orderId,
                $paymentObject,
                $order,
                $customer_id,
                $test_mode
            );

            $this->saveMollieInfo( $order, $paymentObject );

            if ($dataHelper->isSubscription($orderId)) {
                $mandates = Plugin::getApiHelper()->getApiClient( $test_mode )->customers->get( $customer_id )->mandates();
                $mandate = $mandates[0];
                $customerId = $mandate->customerId;
                $mandateId = $mandate->id;
                $this->logger->log( \WC_Log_Levels::DEBUG, "Mollie Subscription in the order: customer id {$customerId} and mandate id {$mandateId} ");
                do_action(Plugin::PLUGIN_ID . '_after_mandate_created', $paymentObject, $order, $customerId, $mandateId);
            }

            do_action( Plugin::PLUGIN_ID . '_payment_created', $paymentObject, $order );
            $this->logger->log( \WC_Log_Levels::DEBUG,  $this->id . ': Mollie payment object ' . $paymentObject->id . ' (' . $paymentObject->mode . ') created for order ' . $orderId );

            // Update initial order status for payment methods where the payment status will be delivered after a couple of days.
            // See: https://www.mollie.com/nl/docs/status#expiry-times-per-payment-method
            // Status is only updated if the new status is not the same as the default order status (pending)
            if ( ( $paymentObject->method == 'banktransfer' ) || ( $paymentObject->method == 'directdebit' ) ) {

                // Don't change the status of the order if it's Partially Paid
                // This adds support for WooCommerce Deposits (by Webtomizer)
                // See https://github.com/mollie/WooCommerce/issues/138

                $order_status = $order->get_status();

                if ( $order_status != 'wc-partially-paid ' ) {

                    $this->updateOrderStatus(
                        $order,
                        $initial_order_status,
                        __( 'Awaiting payment confirmation.', 'mollie-payments-for-woocommerce' ) . "\n"
                    );

                }
            }

            $paymentMethodTitle = $this->getPaymentMethodTitle($paymentObject);

            $order->add_order_note( sprintf(
                                    /* translators: Placeholder 1: Payment method title, placeholder 2: payment ID */
                                        __( '%s payment started (%s).', 'mollie-payments-for-woocommerce' ),
                                        $paymentMethodTitle,
                                        $paymentObject->id . ( $paymentObject->mode == 'test' ? ( ' - ' . __( 'test mode', 'mollie-payments-for-woocommerce' ) ) : '' )
                                    ) );

            $this->logger->log( \WC_Log_Levels::DEBUG,  "For order " . $orderId . " redirect user to Mollie Checkout URL: " . $paymentObject->getCheckoutUrl() );


            return array (
                'result'   => 'success',
                'redirect' => $this->gateway->getProcessPaymentRedirect( $order, $paymentObject ),
            );
        }
        catch ( Mollie\Api\Exceptions\ApiException $e ) {
            $this->logger->log( \WC_Log_Levels::DEBUG,  $this->id . ': Failed to create Mollie payment object for order ' . $orderId . ': ' . $e->getMessage() );

            /* translators: Placeholder 1: Payment method title */
            $message = sprintf( __( 'Could not create %s payment.', 'mollie-payments-for-woocommerce' ), $this->title );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                $message .= 'hii ' . $e->getMessage();
            }

            $this->notice->addNotice('error', $message);
        }

        return array ( 'result' => 'failure' );
    }

    /**
     * @return string
     */
    protected function getInitialOrderStatus ($paymentConfirmationAfterCoupleOfDays)
    {
        if ($paymentConfirmationAfterCoupleOfDays)
        {
            return $this->gateway->get_option('initial_order_status');
        }

        return AbstractGateway::STATUS_PENDING;
    }
    /**
     * @param $order
     * @param $test_mode
     * @return null|string
     */
    protected function getUserMollieCustomerId($order, $test_mode)
    {
        $order_customer_id = $order->get_customer_id();

        return  Plugin::getDataHelper()->getUserMollieCustomerId($order_customer_id, $test_mode);
    }

    protected function paymentTypeBasedOnGateway()
    {
        $optionName = Plugin::PLUGIN_ID . '_' .'api_switch';
        $apiSwitchOption = get_option($optionName);
        $paymentType = $apiSwitchOption?: AbstractGateway::PAYMENT_METHOD_TYPE_ORDER;
        $isBankTransferGateway = $this->gateway->id == 'mollie_wc_gateway_banktransfer';
        if($isBankTransferGateway && $this->gateway->isExpiredDateSettingActivated()){
            $paymentType = AbstractGateway::PAYMENT_METHOD_TYPE_PAYMENT;
        }

        return $paymentType;
    }
    /**
     * CHECK WOOCOMMERCE PRODUCTS
     * Make sure all cart items are real WooCommerce products,
     * not removed products or virtual ones (by WooCommerce Events Manager etc).
     * If products are virtual, use Payments API instead of Orders API
     *
     * @param \WC_Order $order
     *
     * @param  string  $molliePaymentType
     *
     * @return string
     */
    protected function paymentTypeBasedOnProducts(\WC_Order $order, $molliePaymentType)
    {
        foreach ($order->get_items() as $cart_item) {
            if ($cart_item['quantity']) {
                do_action(
                    Plugin::PLUGIN_ID
                    . '_orderlines_process_items_before_getting_product_id',
                    $cart_item
                );

                if ($cart_item['variation_id']) {
                    $product = wc_get_product($cart_item['variation_id']);
                } else {
                    $product = wc_get_product($cart_item['product_id']);
                }

                if ($product == false) {
                    $molliePaymentType = AbstractGateway::PAYMENT_METHOD_TYPE_PAYMENT;
                    do_action(
                        Plugin::PLUGIN_ID
                        . '_orderlines_process_items_after_processing_item',
                        $cart_item
                    );
                    break;
                }
                do_action(
                    Plugin::PLUGIN_ID
                    . '_orderlines_process_items_after_processing_item',
                    $cart_item
                );
            }
        }
        return $molliePaymentType;
    }
    /**
     * @param MollieOrder $paymentObject
     * @param \WC_Order                $order
     * @param                         $customer_id
     * @param                         $test_mode
     *
     * @return array
     * @throws ApiException
     */
    protected function processAsMollieOrder(
        MollieOrder $paymentObject,
        \WC_Order $order,
        $customer_id,
        $test_mode
    ) {
        $molliePaymentType = AbstractGateway::PAYMENT_METHOD_TYPE_ORDER;
        $paymentRequestData = $paymentObject->getPaymentRequestData(
            $order,
            $customer_id
        );

        $data = array_filter($paymentRequestData);

        $data = apply_filters(
            'woocommerce_' . $this->id . '_args',
            $data,
            $order
        );

        do_action(
            Plugin::PLUGIN_ID . '_create_payment',
            $data,
            $order
        );

        // Create Mollie payment with customer id.
        try {
            $this->logger->log( \WC_Log_Levels::DEBUG,
                'Creating payment object: type Order, first try creating a Mollie Order.'
            );

            // Only enable this for hardcore debugging!
            $apiCallLog = [
                'amount' => isset($data['amount']) ? $data['amount'] : '',
                'redirectUrl' => isset($data['redirectUrl'])
                    ? $data['redirectUrl'] : '',
                'webhookUrl' => isset($data['webhookUrl'])
                    ? $data['webhookUrl'] : '',
                'method' => isset($data['method']) ? $data['method'] : '',
                'payment' => isset($data['payment']) ? $data['payment']
                    : '',
                'locale' => isset($data['locale']) ? $data['locale'] : '',
                'metadata' => isset($data['metadata']) ? $data['metadata']
                    : '',
                'orderNumber' => isset($data['orderNumber'])
                    ? $data['orderNumber'] : ''
            ];

            $this->logger->log( \WC_Log_Levels::DEBUG, $apiCallLog);
            $paymentOrder = $paymentObject;
            $paymentObject = Plugin::getApiHelper()->getApiClient( $test_mode )->orders->create( $data );
            $settingsHelper = Plugin::getSettingsHelper();
            if($settingsHelper->getOrderStatusCancelledPayments() == 'cancelled'){
                $orderId = $order->get_id();
                $orderWithPayments = Plugin::getApiHelper()->getApiClient( $test_mode )->orders->get( $paymentObject->id, [ "embed" => "payments" ] );
                $paymentOrder->updatePaymentDataWithOrderData($orderWithPayments, $orderId);
            }
        } catch (Mollie\Api\Exceptions\ApiException $e) {
            // Don't try to create a Mollie Payment for Klarna payment methods
            $order_payment_method = $order->get_payment_method();

            if ($order_payment_method == 'mollie_wc_gateway_klarnapaylater'
                || $order_payment_method == 'mollie_wc_gateway_sliceit'
            ) {
                $this->logger->log( \WC_Log_Levels::DEBUG,
                    'Creating payment object: type Order, failed for Klarna payment, stopping process.'
                );
                throw $e;
            }

            $this->logger->log( \WC_Log_Levels::DEBUG,
                'Creating payment object: type Order, first try failed: '
                . $e->getMessage()
            );

            // Unset missing customer ID
            unset($data['payment']['customerId']);

            try {
                if ($e->getField() !== 'payment.customerId') {
                    $this->logger->log( \WC_Log_Levels::DEBUG,
                        'Creating payment object: type Order, did not fail because of incorrect customerId, so trying Payment now.'
                    );
                    throw $e;
                }

                // Retry without customer id.
                $this->logger->log( \WC_Log_Levels::DEBUG,
                    'Creating payment object: type Order, second try, creating a Mollie Order without a customerId.'
                );
                $paymentObject = Plugin::getApiHelper()->getApiClient(
                    $test_mode
                )->orders->create($data);
            } catch (Mollie\Api\Exceptions\ApiException $e) {
                // Set Mollie payment type to payment, when creating a Mollie Order has failed
                $molliePaymentType = AbstractGateway::PAYMENT_METHOD_TYPE_PAYMENT;
            }
        }
        return array(
            $paymentObject,
            $molliePaymentType
        );
    }

    /**
     * @param \WC_Order                $order
     * @param                         $customer_id
     * @param                         $test_mode
     *
     * @return Mollie\Api\Resources\Payment $paymentObject
     * @throws ApiException
     */
    protected function processAsMolliePayment(
        \WC_Order $order,
        $customer_id,
        $test_mode
    ) {
        $paymentObject = Plugin::getPaymentFactoryHelper()->getPaymentObject(
            AbstractGateway::PAYMENT_METHOD_TYPE_PAYMENT
        );
        $paymentRequestData = $paymentObject->getPaymentRequestData(
            $order,
            $customer_id
        );

        $data = array_filter($paymentRequestData);

        $data = apply_filters(
            'woocommerce_' . $this->gateway->id . '_args',
            $data,
            $order
        );

        try {
            // Only enable this for hardcore debugging!
            $apiCallLog = [
                'amount' => isset($data['amount']) ? $data['amount'] : '',
                'description' => isset($data['description'])
                    ? $data['description'] : '',
                'redirectUrl' => isset($data['redirectUrl'])
                    ? $data['redirectUrl'] : '',
                'webhookUrl' => isset($data['webhookUrl'])
                    ? $data['webhookUrl'] : '',
                'method' => isset($data['method']) ? $data['method'] : '',
                'issuer' => isset($data['issuer']) ? $data['issuer'] : '',
                'locale' => isset($data['locale']) ? $data['locale'] : '',
                'dueDate' => isset($data['dueDate']) ? $data['dueDate'] : '',
                'metadata' => isset($data['metadata']) ? $data['metadata']
                    : ''
            ];

            $this->logger->log( \WC_Log_Levels::DEBUG, $apiCallLog);

            // Try as simple payment
            $paymentObject = Plugin::getApiHelper()->getApiClient(
                $test_mode
            )->payments->create($data);
        } catch (Mollie\Api\Exceptions\ApiException $e) {
            $message = $e->getMessage();
            $this->logger->log( \WC_Log_Levels::DEBUG, $message);
            throw $e;
        }
        return $paymentObject;
    }

    /**
     * @param                         $molliePaymentType
     * @param                         $orderId
     * @param MollieOrder|MolliePayment $paymentObject
     * @param \WC_Order                $order
     * @param                         $customer_id
     * @param                         $test_mode
     *
     * @return mixed|Payment|MollieOrder
     * @throws ApiException
     */
    protected function processPaymentForMollie(
        $molliePaymentType,
        $orderId,
        $paymentObject,
        \WC_Order $order,
        $customer_id,
        $test_mode
    ) {
        //
        // PROCESS REGULAR PAYMENT AS MOLLIE ORDER
        //
        if ($molliePaymentType == AbstractGateway::PAYMENT_METHOD_TYPE_ORDER) {
            $this->logger->log( \WC_Log_Levels::DEBUG,
                "{$this->gateway->id}: Create Mollie payment object for order {$orderId}",
                [true]
            );

            list(
                $paymentObject,
                $molliePaymentType
                )
                = $this->processAsMollieOrder(
                $paymentObject,
                $order,
                $customer_id,
                $test_mode
            );
        }

        //
        // PROCESS REGULAR PAYMENT AS MOLLIE PAYMENT
        //

        if ($molliePaymentType === AbstractGateway::PAYMENT_METHOD_TYPE_PAYMENT) {
            $this->logger->log( \WC_Log_Levels::DEBUG,
                'Creating payment object: type Payment, creating a Payment.'
            );

            $paymentObject = $this->processAsMolliePayment(
                $order,
                $customer_id,
                $test_mode
            );
        }
        return $paymentObject;
    }

    /**
     * @param $order
     * @param $payment
     */
    protected function saveMollieInfo( $order, $payment ) {
        // Get correct Mollie Payment Object
        $payment_object = Plugin::getPaymentFactoryHelper()->getPaymentObject( $payment );

        // Set active Mollie payment
        $payment_object->setActiveMolliePayment( $order->get_id() );

        // Get Mollie Customer ID
        $mollie_customer_id = $payment_object->getMollieCustomerIdFromPaymentObject( $payment_object->data->id );

        // Set Mollie customer
        Plugin::getDataHelper()->setUserMollieCustomerId( $order->get_customer_id(), $mollie_customer_id );
    }

    //refactor

    /**
     * @param $payment
     * @return string
     */
    protected function getPaymentMethodTitle($payment)
    {

        // TODO David: this needs to be updated, doesn't work in all cases?
        $payment_method_title = '';
        if ($payment->method == $this->gateway->getMollieMethodId()){
            $payment_method_title = $this->gateway->method_title;
        }
        return $payment_method_title;
    }
    /**
     * @param \WC_Order $order
     * @param string $new_status
     * @param string $note
     * @param bool $restore_stock
     */
    public function updateOrderStatus (\WC_Order $order, $new_status, $note = '', $restore_stock = true )
    {
        $order->update_status($new_status, $note);

        switch ($new_status)
        {
            case AbstractGateway::STATUS_ON_HOLD:

                if ( $restore_stock == true ) {
                    if ( ! $order->get_meta( '_order_stock_reduced', true ) ) {
                        // Reduce order stock
                        wc_reduce_stock_levels( $order->get_id() );

                        $this->logger->log( \WC_Log_Levels::DEBUG,  __METHOD__ . ":  Stock for order {$order->get_id()} reduced." );
                    }
                }

                break;

            case AbstractGateway::STATUS_PENDING:
            case AbstractGateway::STATUS_FAILED:
            case AbstractGateway::STATUS_CANCELLED:
                if ( $order->get_meta( '_order_stock_reduced', true ) )
                {
                    // Restore order stock
                    Plugin::getDataHelper()->restoreOrderStock($order);

                    $this->logger->log( \WC_Log_Levels::DEBUG, __METHOD__ . " Stock for order {$order->get_id()} restored.");
                }

                break;
        }
    }
}
