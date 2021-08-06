<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment;

use Mollie\Api\Resources\Refund;
use Mollie\WooCommerce\Gateway\AbstractGateway;
use Mollie\WooCommerce\Plugin;
use WC_Order;
use WC_Payment_Gateway;
use WC_Subscriptions_Manager;

class MolliePayment extends MollieObject
{

    const ACTION_AFTER_REFUND_PAYMENT_CREATED = Plugin::PLUGIN_ID . '_refund_payment_created';

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getPaymentObject($paymentId, $testMode = false, $useCache = true)
    {
        try {
            // Is test mode enabled?
            $settingsHelper = Plugin::getSettingsHelper();
            $testMode = $settingsHelper->isTestModeEnabled();

            self::$payment = Plugin::getApiHelper()->getApiClient($testMode)->payments->get($paymentId);

            return parent::getPaymentObject($paymentId, $testMode = false, $useCache = true);
        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            $this->logger->log( \WC_Log_Levels::DEBUG, __FUNCTION__ . ": Could not load payment $paymentId (" . ( $testMode ? 'test' : 'live' ) . "): " . $e->getMessage() . ' (' . get_class($e) . ')');
        }

        return null;
    }

    /**
     * @param $order
     * @param $customerId
     *
     * @return array
     */
    public function getPaymentRequestData($order, $customerId)
    {
        $settingsHelper = Plugin::getSettingsHelper();
        $optionName = Plugin::PLUGIN_ID . '_' .'api_payment_description';
        $option = get_option($optionName);
        $paymentDescription = $this->getPaymentDescription($order, $option);
        $paymentLocale = $settingsHelper->getPaymentLocale();
        $storeCustomer = $settingsHelper->shouldStoreCustomer();

        $gateway = wc_get_payment_gateway_by_order($order);

        if (!$gateway || !($gateway instanceof AbstractGateway)) {
            return ['result' => 'failure'];
        }

        $mollieMethod = $gateway->getMollieMethodId();
        $selectedIssuer = $gateway->getSelectedIssuer();
        $returnUrl = $gateway->getReturnUrl($order);
        $webhookUrl = $gateway->getWebhookUrl($order);
        $orderId = $order->get_id();

        $paymentRequestData = [
            'amount' => [
                'currency' => Plugin::getDataHelper()
                    ->getOrderCurrency($order),
                'value' => Plugin::getDataHelper()
                    ->formatCurrencyValue(
                        $order->get_total(),
                        Plugin::getDataHelper()->getOrderCurrency(
                            $order
                        )
                    ),
            ],
            'description' => $paymentDescription,
            'redirectUrl' => $returnUrl,
            'webhookUrl' => $webhookUrl,
            'method' => $mollieMethod,
            'issuer' => $selectedIssuer,
            'locale' => $paymentLocale,
            'metadata' => [
                'order_id' => $orderId,
            ],
        ];

        // Add sequenceType for subscriptions first payments
        if (class_exists('WC_Subscriptions')
            && class_exists(
                'WC_Subscriptions_Admin'
            )
        ) {
            if (Plugin::getDataHelper()->isWcSubscription($orderId)) {
                // See get_available_payment_gateways() in woocommerce-subscriptions/includes/gateways/class-wc-subscriptions-payment-gateways.php
                $disableAutomaticPayments = ('yes' == get_option(
                    WC_Subscriptions_Admin::$option_prefix
                        . '_turn_off_automatic_payments',
                    'no'
                )) ? true : false;
                $supportsSubscriptions = $gateway->supports('subscriptions');

                if ($supportsSubscriptions == true
                    && $disableAutomaticPayments == false
                ) {
                    $paymentRequestData['sequenceType'] = 'first';
                }
            }
        }

        if ($storeCustomer) {
            $paymentRequestData['customerId'] = $customerId;
        }

        $cardToken = mollieWooCommerceCardToken();
        if ($cardToken) {
            $paymentRequestData['cardToken'] = $cardToken;
        }

        if (isset($_POST['token'])) {
            $applePayToken = $_POST['token'];
            $applePayToken = filter_var($applePayToken, FILTER_SANITIZE_STRING);
            $encodedApplePayToken = json_encode($applePayToken);
            $paymentRequestData['applePayPaymentToken'] = $encodedApplePayToken;
        }
        return $paymentRequestData;
    }

    protected function getPaymentDescription($order, $option)
    {
        switch ($option) {
            case '{orderNumber}':
                $description = 'Order ' . $order->get_order_number();
                break;
            case '{storeName}':
                $description = 'StoreName ' . get_bloginfo('name');
                break;
            case '{customer.firstname}':
                $description = 'Customer Firstname '
                    . $order->get_billing_first_name();
                break;
            case '{customer.lastname}':
                $description = 'Customer Lastname '
                    . $order->get_billing_last_name();
                break;
            case '{customer.company}':
                $description = 'Customer Company '
                    . $order->get_billing_company();
                break;
            default:
                $description = 'Order ' . $order->get_order_number();
        }
        return $description;
    }

    public function setActiveMolliePayment($orderId)
    {
        parent::setActiveMolliePayment($orderId);
        self::$paymentId = $this->getMolliePaymentIdFromPaymentObject();
        self::$customerId = $this->getMollieCustomerIdFromPaymentObject();
        self::$order = wc_get_order($orderId);

        self::$order->update_meta_data('_mollie_payment_id', $this->data->id);
        self::$order->update_meta_data('_mollie_order_id', false);
        self::$order->save();

        return $this;
    }

    public function getMolliePaymentIdFromPaymentObject()
    {
        if (isset($this->data->id)) {
            return $this->data->id;
        }

        return null;
    }

    public function getMollieCustomerIdFromPaymentObject($payment = null)
    {
        if ($payment == null) {
            $payment = $this->data->id;
        }

        $payment = $this->getPaymentObject($payment);

        if (isset($payment->customerId)) {
            return $payment->customerId;
        }

        return null;
    }

    public function getSequenceTypeFromPaymentObject($payment = null)
    {
        if ($payment == null) {
            $payment = $this->data->id;
        }

        $payment = $this->getPaymentObject($payment);

        if (isset($payment->sequenceType)) {
            return $payment->sequenceType;
        }

        return null;
    }

    public function getMollieCustomerIbanDetailsFromPaymentObject($payment = null)
    {
        if ($payment == null) {
            $payment = $this->data->id;
        }

        $payment = $this->getPaymentObject($payment);

        $ibanDetails['consumerName'] = $payment->details->consumerName;
        $ibanDetails['consumerAccount'] = $payment->details->consumerAccount;

        return $ibanDetails;
    }

    /**
     * @param \WC_Order                     $order
     * @param Mollie\Api\Resources\Payment $payment
     * @param string                       $paymentMethodTitle
     */
    public function onWebhookPaid(WC_Order $order, $payment, $paymentMethodTitle)
    {
        $orderId = $order->get_id();
        if ($payment->isPaid()) {
            // Add messages to log
            $this->logger->log( \WC_Log_Levels::DEBUG, __METHOD__ . ' called for payment ' . $orderId);

            if ($payment->method === 'paypal') {
                $this->addPaypalTransactionIdToOrder($order);
            }

            // WooCommerce 2.2.0 has the option to store the Payment transaction id.
            $order->payment_complete($payment->id);

            // Add messages to log
            $this->logger->log( \WC_Log_Levels::DEBUG, __METHOD__ . ' WooCommerce payment_complete() processed and returned to ' . __METHOD__ . ' for payment ' . $orderId);

            $order->add_order_note(sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                __('Order completed using %1$s payment (%2$s).', 'mollie-payments-for-woocommerce'),
                $paymentMethodTitle,
                $payment->id . ( $payment->mode == 'test' ? ( ' - ' . __('test mode', 'mollie-payments-for-woocommerce') ) : '' )
            ));

            // Mark the order as processed and paid via Mollie
            $this->setOrderPaidAndProcessed($order);

            // Remove (old) cancelled payments from this order
            $this->unsetCancelledMolliePaymentId($orderId);

            // Add messages to log
            $this->logger->log( \WC_Log_Levels::DEBUG, __METHOD__ . ' processing paid payment via Mollie plugin fully completed for order ' . $orderId);

            // Subscription processing
            if (class_exists('WC_Subscriptions') && class_exists('WC_Subscriptions_Admin')) {
                if (Plugin::getDataHelper()->isWcSubscription($orderId)) {
                    $this->deleteSubscriptionOrderFromPendingPaymentQueue($order);
                    WC_Subscriptions_Manager::activate_subscriptions_for_order($order);
                }
            }
        } else {
            // Add messages to log
            $this->logger->log( \WC_Log_Levels::DEBUG, __METHOD__ . ' payment at MollieSettingsPage not paid, so no processing for order ' . $orderId);
        }
    }

    /**
     * @param WC_Order                     $order
     * @param Mollie\Api\Resources\Payment $payment
     * @param string                       $paymentMethodTitle
     */
    public function onWebhookCanceled(WC_Order $order, $payment, $paymentMethodTitle)
    {
        // Get order ID in the correct way depending on WooCommerce version
        $orderId = $order->get_id();

        // Add messages to log
        $this->logger->log( \WC_Log_Levels::DEBUG, __METHOD__ . " called for payment {$orderId}");

        // if the status is Completed|Refunded|Cancelled  DONT change the status to cancelled
        if ($this->isFinalOrderStatus($order)) {
            $this->logger->log( \WC_Log_Levels::DEBUG,
                __METHOD__
                . " called for payment {$orderId} has final status. Nothing to be done"
            );

            return;
        }

        //status is Pending|Failed|Processing|On-hold so Cancel
        $this->unsetActiveMolliePayment($orderId, $payment->id);
        $this->setCancelledMolliePaymentId($orderId, $payment->id);

        // What status does the user want to give orders with cancelled payments?
        $settingsHelper = Plugin::getSettingsHelper();
        $orderStatusCancelledPayments = $settingsHelper->getOrderStatusCancelledPayments();

        // New order status
        if ($orderStatusCancelledPayments == 'pending' || $orderStatusCancelledPayments == null) {
            $newOrderStatus = AbstractGateway::STATUS_PENDING;
        } elseif ($orderStatusCancelledPayments == 'cancelled') {
            $newOrderStatus = AbstractGateway::STATUS_CANCELLED;
        }
        // if I cancel manually the order is canceled in Woo before calling MollieSettingsPage
        if ($order->get_status() == 'cancelled') {
            $newOrderStatus = AbstractGateway::STATUS_CANCELLED;
        }

        // Get current gateway
        $gateway = wc_get_payment_gateway_by_order($order);
        // Overwrite plugin-wide
        $newOrderStatus = apply_filters(Plugin::PLUGIN_ID . '_order_status_cancelled', $newOrderStatus);

        // Overwrite gateway-wide
        $newOrderStatus = apply_filters(Plugin::PLUGIN_ID . '_order_status_cancelled_' . $gateway->id, $newOrderStatus);

        // Update order status, but only if there is no payment started by another gateway
        $this->maybeUpdateStatus($order, $gateway, $newOrderStatus, $orderId);

        // User cancelled payment on MollieSettingsPage or issuer page, add a cancel note.. do not cancel order.
        $order->add_order_note(sprintf(
        /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __('%1$s payment (%2$s) cancelled .', 'mollie-payments-for-woocommerce'),
            $paymentMethodTitle,
            $payment->id . ( $payment->mode == 'test' ? ( ' - ' . __('test mode', 'mollie-payments-for-woocommerce') ) : '' )
        ));

        // Subscription processing
        $this->deleteSubscriptionFromPending($order);
    }

    /**
     * @param WC_Order                     $order
     * @param Mollie\Api\Resources\Payment $payment
     * @param string                       $paymentMethodTitle
     */
    public function onWebhookFailed(WC_Order $order, $payment, $paymentMethodTitle)
    {
        $orderId = $order->get_id();

        // Add messages to log
        $this->logger->log( \WC_Log_Levels::DEBUG, __METHOD__ . ' called for order ' . $orderId);

        // Get current gateway
        $gateway = wc_get_payment_gateway_by_order($order);

        // New order status
        $newOrderStatus = AbstractGateway::STATUS_FAILED;

        // Overwrite plugin-wide
        $newOrderStatus = apply_filters(Plugin::PLUGIN_ID . '_order_status_failed', $newOrderStatus);

        // Overwrite gateway-wide
        $newOrderStatus = apply_filters(Plugin::PLUGIN_ID . '_order_status_failed_' . $gateway->id, $newOrderStatus);

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

        $this->logger->log( \WC_Log_Levels::DEBUG, __METHOD__ . ' called for order ' . $orderId . ' and payment ' . $payment->id . ', regular payment failed.');
    }

    /**
     * @param WC_Order                     $order
     * @param Mollie\Api\Resources\Payment $payment
     * @param string                       $paymentMethodTitle
     */
    public function onWebhookExpired(WC_Order $order, $payment, $paymentMethodTitle)
    {
        $orderId = $order->get_id();
        $molliePaymentId = $order->get_meta('_mollie_payment_id', true);

        // Add messages to log
        $this->logger->log( \WC_Log_Levels::DEBUG, __METHOD__ . ' called for order ' . $orderId);

        // Get current gateway
        $gateway = wc_get_payment_gateway_by_order($order);

        // Check that this payment is the most recent, based on MollieSettingsPage Payment ID from post meta, do not cancel the order if it isn't
        if ($molliePaymentId != $payment->id) {
            $this->logger->log( \WC_Log_Levels::DEBUG, __METHOD__ . ' called for order ' . $orderId . ' and payment ' . $payment->id . ', not processed because of a newer pending payment ' . $molliePaymentId);

            $order->add_order_note(sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                __('%1$s payment expired (%2$s) but not cancelled because of another pending payment (%3$s).', 'mollie-payments-for-woocommerce'),
                $paymentMethodTitle,
                $payment->id . ( $payment->mode == 'test' ? ( ' - ' . __('test mode', 'mollie-payments-for-woocommerce') ) : '' ),
                $molliePaymentId
            ));

            return;
        }

        // New order status
        $newOrderStatus = AbstractGateway::STATUS_CANCELLED;

        // Overwrite plugin-wide
        $newOrderStatus = apply_filters(Plugin::PLUGIN_ID . '_order_status_expired', $newOrderStatus);

        // Overwrite gateway-wide
        $newOrderStatus = apply_filters(Plugin::PLUGIN_ID . '_order_status_expired_' . $gateway->id, $newOrderStatus);

        // Update order status, but only if there is no payment started by another gateway
        $this->maybeUpdateStatus($order, $gateway, $newOrderStatus, $orderId);

        $order->add_order_note(sprintf(
        /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __('%1$s payment expired (%2$s).', 'mollie-payments-for-woocommerce'),
            $paymentMethodTitle,
            $payment->id . ( $payment->mode == 'test' ? ( ' - ' . __('test mode', 'mollie-payments-for-woocommerce') ) : '' )
        ));

        // Remove (old) cancelled payments from this order
        $this->unsetCancelledMolliePaymentId($orderId);
    }

    /**
     * Process a payment object refund
     *
     * @param object $order
     * @param int    $orderId
     * @param object $paymentObject
     * @param null   $amount
     * @param string $reason
     *
     * @return bool | \WP_Error
     */
    public function refund(\WC_Order $order, $orderId, $paymentObject, $amount = null, $reason = '')
    {
        $this->logger->log( \WC_Log_Levels::DEBUG, __METHOD__ . ' - ' . $orderId . ' - Try to process refunds for individual order line(s).');

        try {
            $paymentObject = Plugin::getPaymentObject()->getActiveMolliePayment($orderId);

            if (! $paymentObject) {
                $errorMessage = "Could not find active MollieSettingsPage payment for WooCommerce order ' . $orderId";

                $this->logger->log( \WC_Log_Levels::DEBUG, __METHOD__ . ' - ' . $errorMessage);

                return new WP_Error('1', $errorMessage);
            }

            if (! $paymentObject->isPaid()) {
                $errorMessage = "Can not refund payment $paymentObject->id for WooCommerce order $orderId as it is not paid.";

                $this->logger->log( \WC_Log_Levels::DEBUG, __METHOD__ . ' - ' . $errorMessage);

                return new WP_Error('1', $errorMessage);
            }

            $this->logger->log( \WC_Log_Levels::DEBUG, __METHOD__ . ' - Create refund - payment object: ' . $paymentObject->id . ', WooCommerce order: ' . $orderId . ', amount: ' . Plugin::getDataHelper()->getOrderCurrency($order) . $amount . ( ! empty($reason) ? ', reason: ' . $reason : '' ));

            do_action(Plugin::PLUGIN_ID . '_create_refund', $paymentObject, $order);

            // Is test mode enabled?
            $testMode = Plugin::getSettingsHelper()->isTestModeEnabled();

            // Send refund to MollieSettingsPage
            $refund = Plugin::getApiHelper()->getApiClient($testMode)->payments->refund($paymentObject, [
                'amount' =>  [
                    'currency' => Plugin::getDataHelper()->getOrderCurrency($order),
                    'value' => Plugin::getDataHelper()->formatCurrencyValue($amount, Plugin::getDataHelper()->getOrderCurrency($order)),
                ],
                'description' => $reason,
            ]);

            $this->logger->log( \WC_Log_Levels::DEBUG, __METHOD__ . ' - Refund created - refund: ' . $refund->id . ', payment: ' . $paymentObject->id . ', order: ' . $orderId . ', amount: ' . Plugin::getDataHelper()->getOrderCurrency($order) . $amount . ( ! empty($reason) ? ', reason: ' . $reason : '' ));

            /**
             * After Payment Refund has been created
             *
             * @param Refund $refund
             * @param WC_Order $order
             */
            do_action(self::ACTION_AFTER_REFUND_PAYMENT_CREATED, $refund, $order);

            do_action_deprecated(
                Plugin::PLUGIN_ID . '_refund_created',
                [$refund, $order],
                '5.3.1',
                self::ACTION_AFTER_REFUND_PAYMENT_CREATED
            );

            $order->add_order_note(sprintf(
            /* translators: Placeholder 1: currency, placeholder 2: refunded amount, placeholder 3: optional refund reason, placeholder 4: payment ID, placeholder 5: refund ID */
                __('Refunded %1$s%2$s%3$s - Payment: %4$s, Refund: %5$s', 'mollie-payments-for-woocommerce'),
                Plugin::getDataHelper()->getOrderCurrency($order),
                $amount,
                ( ! empty($reason) ? ' (reason: ' . $reason . ')' : '' ),
                $refund->paymentId,
                $refund->id
            ));

            return true;
        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            return new WP_Error(1, $e->getMessage());
        }
    }

    /**
     * @param WC_Order $order
     * @param WC_Payment_Gateway $gateway
     * @param                    $newOrderStatus
     * @param                    $orderId
     */
    protected function maybeUpdateStatus(
        WC_Order $order,
        WC_Payment_Gateway $gateway,
        $newOrderStatus,
        $orderId
    ) {

        if (!$this->isOrderPaymentStartedByOtherGateway($order)) {
            if ($gateway) {
                $gateway->updateOrderStatus($order, $newOrderStatus);
            }
        } else {
            $this->informNotUpdatingStatus($orderId, $gateway->id, $order);
        }
    }
}
