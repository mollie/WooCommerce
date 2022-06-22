<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment;

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Refund;
use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\SDK\Api;
use Psr\Log\LogLevel;
use WC_Order;
use WC_Subscriptions_Manager;
use WP_Error;

class MolliePayment extends MollieObject
{

    public const ACTION_AFTER_REFUND_PAYMENT_CREATED = 'mollie-payments-for-woocommerce_refund_payment_created';
    protected $pluginId;

    public function __construct($data, $pluginId, Api $apiHelper, $settingsHelper, $dataHelper, $logger)
    {
        $this->data = $data;
        $this->pluginId = $pluginId;
        $this->apiHelper = $apiHelper;
        $this->settingsHelper = $settingsHelper;
        $this->dataHelper = $dataHelper;
        $this->logger = $logger;
    }

    public function getPaymentObject($paymentId, $testMode = false, $useCache = true)
    {
        try {
            // Is test mode enabled?
            $settingsHelper = $this->settingsHelper;
            $testMode = $settingsHelper->isTestModeEnabled();
            $apiKey = $this->settingsHelper->getApiKey();
            self::$payment = $this->apiHelper->getApiClient($apiKey)->payments->get($paymentId);

            return parent::getPaymentObject($paymentId, $testMode = false, $useCache = true);
        } catch (ApiException $e) {
            $this->logger->log(
                LogLevel::DEBUG,
                __FUNCTION__ . ": Could not load payment $paymentId (" . ( $testMode ? 'test' : 'live' ) . "): " . $e->getMessage() . ' (' . get_class($e) . ')'
            );
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
        $settingsHelper = $this->settingsHelper;
        $optionName = $this->pluginId . '_' . 'api_payment_description';
        $option = get_option($optionName);
        $paymentDescription = $this->getPaymentDescription($order, $option);
        $paymentLocale = $settingsHelper->getPaymentLocale();
        $storeCustomer = $settingsHelper->shouldStoreCustomer();

        $gateway = wc_get_payment_gateway_by_order($order);

        if (!$gateway || !($gateway instanceof MolliePaymentGateway)) {
            return ['result' => 'failure'];
        }

        $gatewayId = $gateway->id;
        $selectedIssuer = $gateway->getSelectedIssuer();
        $returnUrl = $gateway->get_return_url($order);
        $returnUrl = $this->getReturnUrl($order, $returnUrl);
        $webhookUrl = $this->getWebhookUrl($order, $gatewayId);
        $orderId = $order->get_id();

        $paymentRequestData = [
            'amount' => [
                'currency' => $this->dataHelper
                    ->getOrderCurrency($order),
                'value' => $this->dataHelper
                    ->formatCurrencyValue(
                        $order->get_total(),
                        $this->dataHelper->getOrderCurrency(
                            $order
                        )
                    ),
            ],
            'description' => $paymentDescription,
            'redirectUrl' => $returnUrl,
            'webhookUrl' => $webhookUrl,
            'method' => $gateway->paymentMethod->getProperty('id'),
            'issuer' => $selectedIssuer,
            'locale' => $paymentLocale,
            'metadata' => [
                'order_id' => $orderId,
            ],
        ];

        $paymentRequestData = $this->addSequenceTypeForSubscriptionsFirstPayments($order->get_id(), $gateway, $paymentRequestData);

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

    public function addSequenceTypeFirst($paymentRequestData)
    {
        $paymentRequestData['sequenceType'] = 'first';
        return $paymentRequestData;
    }

    public function setActiveMolliePayment($orderId)
    {
        self::$paymentId = $this->getMolliePaymentIdFromPaymentObject();
        self::$customerId = $this->getMollieCustomerIdFromPaymentObject();
        self::$order = wc_get_order($orderId);

        self::$order->update_meta_data('_mollie_payment_id', $this->data->id);
        self::$order->save();

        parent::setActiveMolliePayment($orderId);
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
        if ($payment === null) {
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
        if ($payment === null) {
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
        if ($payment === null) {
            $payment = $this->data->id;
        }

        $payment = $this->getPaymentObject($payment);

        $ibanDetails['consumerName'] = $payment->details->consumerName;
        $ibanDetails['consumerAccount'] = $payment->details->consumerAccount;

        return $ibanDetails;
    }

    /**
     * @param \WC_Order                     $order
     * @param \Mollie\Api\Resources\Payment $payment
     * @param string                       $paymentMethodTitle
     */
    public function onWebhookPaid(WC_Order $order, $payment, $paymentMethodTitle)
    {
        $orderId = $order->get_id();
        if ($payment->isPaid()) {
            // Add messages to log
            $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' called for payment ' . $orderId);

            if ($payment->method === 'paypal') {
                $this->addPaypalTransactionIdToOrder($order);
            }

            // WooCommerce 2.2.0 has the option to store the Payment transaction id.
            $order->payment_complete($payment->id);

            // Add messages to log
            $this->logger->log(
                LogLevel::DEBUG,
                __METHOD__ . ' WooCommerce payment_complete() processed and returned to ' . __METHOD__ . ' for payment ' . $orderId
            );

            $order->add_order_note(sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                __('Order completed using %1$s payment (%2$s).', 'mollie-payments-for-woocommerce'),
                $paymentMethodTitle,
                $payment->id . ( $payment->mode === 'test' ? ( ' - ' . __('test mode', 'mollie-payments-for-woocommerce') ) : '' )
            ));

            // Mark the order as processed and paid via Mollie
            $this->setOrderPaidAndProcessed($order);

            // Remove (old) cancelled payments from this order
            $this->unsetCancelledMolliePaymentId($orderId);

            // Add messages to log
            $this->logger->log(
                LogLevel::DEBUG,
                __METHOD__ . ' processing paid payment via Mollie plugin fully completed for order ' . $orderId
            );

            // Subscription processing
            if (class_exists('WC_Subscriptions') && class_exists('WC_Subscriptions_Admin')) {
                if ($this->dataHelper->isWcSubscription($orderId)) {
                    $this->deleteSubscriptionOrderFromPendingPaymentQueue($order);
                    WC_Subscriptions_Manager::activate_subscriptions_for_order($order);
                }
            }
        } else {
            // Add messages to log
            $this->logger->log(
                LogLevel::DEBUG,
                __METHOD__ . ' payment at Mollie not paid, so no processing for order ' . $orderId
            );
        }
    }

    /**
     * @param WC_Order                     $order
     * @param \Mollie\Api\Resources\Payment $payment
     * @param string                       $paymentMethodTitle
     */
    public function onWebhookCanceled(WC_Order $order, $payment, $paymentMethodTitle)
    {
        // Get order ID in the correct way depending on WooCommerce version
        $orderId = $order->get_id();

        // Add messages to log
        $this->logger->log(LogLevel::DEBUG, __METHOD__ . " called for payment {$orderId}");

        // if the status is Completed|Refunded|Cancelled  DONT change the status to cancelled
        if ($this->isFinalOrderStatus($order)) {
            $this->logger->log(
                LogLevel::DEBUG,
                __METHOD__
                . " called for payment {$orderId} has final status. Nothing to be done"
            );

            return;
        }

        //status is Pending|Failed|Processing|On-hold so Cancel
        $this->unsetActiveMolliePayment($orderId, $payment->id);
        $this->setCancelledMolliePaymentId($orderId, $payment->id);

        // What status does the user want to give orders with cancelled payments?
        $settingsHelper = $this->settingsHelper;
        $orderStatusCancelledPayments = $settingsHelper->getOrderStatusCancelledPayments();

        // New order status
        if ($orderStatusCancelledPayments === 'pending' || $orderStatusCancelledPayments === null) {
            $newOrderStatus = MolliePaymentGateway::STATUS_PENDING;
        } elseif ($orderStatusCancelledPayments === 'cancelled') {
            $newOrderStatus = MolliePaymentGateway::STATUS_CANCELLED;
        }
        // if I cancel manually the order is canceled in Woo before calling Mollie
        if ($order->get_status() === 'cancelled') {
            $newOrderStatus = MolliePaymentGateway::STATUS_CANCELLED;
        }

        // Get current gateway
        $gateway = wc_get_payment_gateway_by_order($order);
        /**
         * Overwrite plugin-wide the status when webhook canceled is triggered.
         *
         * @since 2.3.0
         *
         * @param string $newOrderStatus order status on canceled.
         */
        $newOrderStatus = apply_filters($this->pluginId . '_order_status_cancelled', $newOrderStatus);

        /**
         * Overwrite gateway-wide the status when webhook canceled is triggered.
         *
         * @since 2.3.0
         *
         * @param string $newOrderStatus order status on canceled.
         */
        $newOrderStatus = apply_filters($this->pluginId . '_order_status_cancelled_' . $gateway->id, $newOrderStatus);

        // Update order status, but only if there is no payment started by another gateway
        $this->maybeUpdateStatus($order, $gateway, $newOrderStatus, $orderId);

        // User cancelled payment on Mollie or issuer page, add a cancel note.. do not cancel order.
        $order->add_order_note(sprintf(
        /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __('%1$s payment (%2$s) cancelled .', 'mollie-payments-for-woocommerce'),
            $paymentMethodTitle,
            $payment->id . ( $payment->mode === 'test' ? ( ' - ' . __('test mode', 'mollie-payments-for-woocommerce') ) : '' )
        ));

        // Subscription processing
        $this->deleteSubscriptionFromPending($order);
    }

    /**
     * @param WC_Order                     $order
     * @param \Mollie\Api\Resources\Payment $payment
     * @param string                       $paymentMethodTitle
     */
    public function onWebhookFailed(WC_Order $order, $payment, $paymentMethodTitle)
    {
        $orderId = $order->get_id();

        // Add messages to log
        $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' called for order ' . $orderId);

        // Get current gateway
        $gateway = wc_get_payment_gateway_by_order($order);

        // New order status
        $newOrderStatus = MolliePaymentGateway::STATUS_FAILED;

        /**
         * Overwrite plugin-wide the status when webhook failed is triggered.
         *
         * @since 5.1.0
         *
         * @param string $newOrderStatus order status on canceled.
         */
        $newOrderStatus = apply_filters($this->pluginId . '_order_status_failed', $newOrderStatus);

        /**
         * Overwrite gateway-wide the status when webhook failed is triggered.
         *
         * @since 5.1.0
         *
         * @param string $newOrderStatus order status on canceled.
         */
        $newOrderStatus = apply_filters($this->pluginId . '_order_status_failed_' . $gateway->id, $newOrderStatus);

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

        $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' called for order ' . $orderId . ' and payment ' . $payment->id . ', regular payment failed.');
    }

    /**
     * @param WC_Order                     $order
     * @param \Mollie\Api\Resources\Payment $payment
     * @param string                       $paymentMethodTitle
     */
    public function onWebhookExpired(WC_Order $order, $payment, $paymentMethodTitle)
    {
        $orderId = $order->get_id();
        $molliePaymentId = $order->get_meta('_mollie_payment_id', true);

        // Add messages to log
        $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' called for order ' . $orderId);

        // Get current gateway
        $gateway = wc_get_payment_gateway_by_order($order);

        // Check that this payment is the most recent, based on Mollie Payment ID from post meta, do not cancel the order if it isn't
        if ($molliePaymentId != $payment->id) {
            $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' called for order ' . $orderId . ' and payment ' . $payment->id . ', not processed because of a newer pending payment ' . $molliePaymentId);

            $order->add_order_note(sprintf(
            /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
                __('%1$s payment expired (%2$s) but not cancelled because of another pending payment (%3$s).', 'mollie-payments-for-woocommerce'),
                $paymentMethodTitle,
                $payment->id . ( $payment->mode === 'test' ? ( ' - ' . __('test mode', 'mollie-payments-for-woocommerce') ) : '' ),
                $molliePaymentId
            ));

            return;
        }

        // New order status
        $newOrderStatus = MolliePaymentGateway::STATUS_CANCELLED;

        /**
         * Overwrite plugin-wide the status when webhook expired is triggered.
         *
         * @since 2.3.0
         *
         * @param string $newOrderStatus order status on canceled.
         */
        $newOrderStatus = apply_filters($this->pluginId . '_order_status_expired', $newOrderStatus);

        /**
         * Overwrite gateway-wide the status when webhook expired is triggered.
         *
         * @since 2.3.0
         *
         * @param string $newOrderStatus order status on canceled.
         */
        $newOrderStatus = apply_filters($this->pluginId . '_order_status_expired_' . $gateway->id, $newOrderStatus);

        // Update order status, but only if there is no payment started by another gateway
        $this->maybeUpdateStatus($order, $gateway, $newOrderStatus, $orderId);

        $order->add_order_note(sprintf(
        /* translators: Placeholder 1: payment method title, placeholder 2: payment ID */
            __('%1$s payment expired (%2$s).', 'mollie-payments-for-woocommerce'),
            $paymentMethodTitle,
            $payment->id . ( $payment->mode === 'test' ? ( ' - ' . __('test mode', 'mollie-payments-for-woocommerce') ) : '' )
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
     * @return bool | WP_Error
     */
    public function refund(\WC_Order $order, $orderId, $paymentObject, $amount = null, $reason = '')
    {
        $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' - ' . $orderId . ' - Try to process refunds for individual order line(s).');

        try {
            $paymentObject = $this->getActiveMolliePayment($orderId);

            if (! $paymentObject) {
                $errorMessage = "Could not find active Mollie payment for WooCommerce order ' . $orderId";

                $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' - ' . $errorMessage);

                return new WP_Error('1', $errorMessage);
            }

            if (! $paymentObject->isPaid()) {
                $errorMessage = "Can not refund payment $paymentObject->id for WooCommerce order $orderId as it is not paid.";

                $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' - ' . $errorMessage);

                return new WP_Error('1', $errorMessage);
            }

            $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' - Create refund - payment object: ' . $paymentObject->id . ', WooCommerce order: ' . $orderId . ', amount: ' . $this->dataHelper->getOrderCurrency($order) . $amount . ( ! empty($reason) ? ', reason: ' . $reason : '' ));
            /**
             * Action hook after processing the chargeback.
             *
             * @since 2.0.0
             *
             * @param object $payment The Mollie payment.
             * @param WC_Order $order The WooCommerce order.
             */
            do_action($this->pluginId . '_create_refund', $paymentObject, $order);

            $apiKey = $this->settingsHelper->getApiKey();
            // Send refund to Mollie
            $refund = $this->apiHelper->getApiClient($apiKey)->payments->refund($paymentObject, [
                'amount' =>  [
                    'currency' => $this->dataHelper->getOrderCurrency($order),
                    'value' => $this->dataHelper->formatCurrencyValue($amount, $this->dataHelper->getOrderCurrency($order)),
                ],
                'description' => $reason,
            ]);

            $this->logger->log(LogLevel::DEBUG, __METHOD__ . ' - Refund created - refund: ' . $refund->id . ', payment: ' . $paymentObject->id . ', order: ' . $orderId . ', amount: ' . $this->dataHelper->getOrderCurrency($order) . $amount . ( ! empty($reason) ? ', reason: ' . $reason : '' ));

            /**
             * After Payment Refund has been created
             *
             * @since 5.3.1
             *
             * @param Refund $refund
             * @param WC_Order $order
             */
            do_action(self::ACTION_AFTER_REFUND_PAYMENT_CREATED, $refund, $order);
            /**
             * After Payment Refund has been created
             *
             * @since 2.0.0
             *
             * @param Refund $refund
             * @param WC_Order $order
             */
            do_action_deprecated(
                $this->pluginId . '_refund_created',
                [$refund, $order],
                '5.3.1',
                self::ACTION_AFTER_REFUND_PAYMENT_CREATED
            );

            $order->add_order_note(sprintf(
            /* translators: Placeholder 1: currency, placeholder 2: refunded amount, placeholder 3: optional refund reason, placeholder 4: payment ID, placeholder 5: refund ID */
                __('Refunded %1$s%2$s%3$s - Payment: %4$s, Refund: %5$s', 'mollie-payments-for-woocommerce'),
                $this->dataHelper->getOrderCurrency($order),
                $amount,
                ( ! empty($reason) ? ' (reason: ' . $reason . ')' : '' ),
                $refund->paymentId,
                $refund->id
            ));

            return true;
        } catch (ApiException $e) {
            return new WP_Error(1, $e->getMessage());
        }
    }

    /**
     * @param WC_Order $order
     * @param MolliePaymentGateway $gateway
     * @param                    $newOrderStatus
     * @param                    $orderId
     */
    protected function maybeUpdateStatus(
        WC_Order $order,
        MolliePaymentGateway $gateway,
        $newOrderStatus,
        $orderId
    ) {
        if ($this->isOrderPaymentStartedByOtherGateway($order) || !$gateway) {
            $this->informNotUpdatingStatus($orderId, $gateway->id, $order);
            return;
        }
        $gateway->paymentService->updateOrderStatus($order, $newOrderStatus);
    }
}
